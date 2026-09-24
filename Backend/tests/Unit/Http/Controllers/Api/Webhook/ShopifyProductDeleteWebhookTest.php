<?php

namespace Tests\Unit\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Api\Webhook\ShopifyController;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use App\Services\ImpuestosService;
use App\Services\ShippingService;
use App\Services\ShopifyImageService;
use App\Services\ShopifySyncCache;
use App\Services\ShopifyTransformer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class ShopifyProductDeleteWebhookTest extends TestCase
{
    private ShopifyController $controller;
    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        Schema::create('empresas', function ($table) {
            $table->id();
            $table->boolean('shopify_sync_bidirectional')->default(false);
            $table->boolean('importacion_productos_shopify')->default(true);
            $table->string('shopify_store_url')->nullable();
            $table->string('shopify_access_token')->nullable();
            $table->string('shopify_consumer_secret')->nullable();
            $table->string('woocommerce_api_key')->nullable();
            $table->string('shopify_status')->nullable();
            $table->text('custom_empresa')->nullable();
            $table->timestamps();
        });

        Schema::create('productos', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_categoria')->nullable();
            $table->unsignedBigInteger('shopify_product_id')->nullable();
            $table->unsignedBigInteger('shopify_variant_id')->nullable();
            $table->unsignedBigInteger('shopify_inventory_item_id')->nullable();
            $table->string('shopify_sku')->nullable();
            $table->string('codigo')->nullable();
            $table->string('nombre')->nullable();
            $table->string('nombre_variante')->nullable();
            $table->decimal('precio', 10, 2)->default(0);
            $table->decimal('costo', 10, 2)->default(0);
            $table->boolean('syncing_from_shopify')->default(false);
            $table->timestamp('last_shopify_sync')->nullable();
            $table->string('enable')->default('1');
            $table->softDeletes();
            $table->timestamps();
        });

        $impuestosService = $this->createMock(ImpuestosService::class);
        $transformer = new ShopifyTransformer($impuestosService);
        $cache = $this->createMock(ShopifySyncCache::class);
        $shippingService = $this->createMock(ShippingService::class);
        $imageService = $this->createMock(ShopifyImageService::class);

        $this->controller = new ShopifyController(
            $transformer,
            $cache,
            $shippingService,
            $impuestosService,
            $imageService
        );

        $this->empresa = Empresa::create([
            'shopify_status' => 'connected',
            'shopify_sync_bidirectional' => false,
        ]);
    }

    public function test_procesar_producto_eliminado_exitoso_producto_simple(): void
    {
        $producto = Producto::create([
            'id_empresa' => $this->empresa->id,
            'nombre' => 'Camisa Azul',
            'codigo' => 'CAM-AZUL',
            'shopify_product_id' => 123456,
            'shopify_variant_id' => 654321,
            'enable' => '1',
        ]);

        $request = new Request([
            'id' => 123456,
        ]);

        $ref = new ReflectionClass($this->controller);
        $method = $ref->getMethod('procesarProductoEliminadoShopify');
        $method->setAccessible(true);

        $response = $method->invoke($this->controller, $request, $this->empresa);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('success', $data['status']);
        $this->assertSame(1, $data['productos_afectados']);

        $productoActualizado = Producto::find($producto->id);
        $this->assertSame('0', (string) $productoActualizado->enable);
        // Verifica que conserve los identificadores para trazabilidad histórica
        $this->assertSame(123456, (int) $productoActualizado->shopify_product_id);
        $this->assertSame(654321, (int) $productoActualizado->shopify_variant_id);
    }

    public function test_procesar_producto_eliminado_exitoso_multiples_variantes(): void
    {
        $var1 = Producto::create([
            'id_empresa' => $this->empresa->id,
            'nombre' => 'Zapatos Deportivos - Talla 40',
            'codigo' => 'ZAP-40',
            'shopify_product_id' => 888999,
            'shopify_variant_id' => 111,
            'enable' => '1',
        ]);

        $var2 = Producto::create([
            'id_empresa' => $this->empresa->id,
            'nombre' => 'Zapatos Deportivos - Talla 42',
            'codigo' => 'ZAP-42',
            'shopify_product_id' => 888999,
            'shopify_variant_id' => 222,
            'enable' => '1',
        ]);

        $request = new Request([
            'id' => 888999,
        ]);

        $ref = new ReflectionClass($this->controller);
        $method = $ref->getMethod('procesarProductoEliminadoShopify');
        $method->setAccessible(true);

        $response = $method->invoke($this->controller, $request, $this->empresa);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('success', $data['status']);
        $this->assertSame(2, $data['productos_afectados']);

        $v1 = Producto::find($var1->id);
        $v2 = Producto::find($var2->id);

        $this->assertSame('0', (string) $v1->enable);
        $this->assertSame('0', (string) $v2->enable);
        $this->assertSame(888999, (int) $v1->shopify_product_id);
        $this->assertSame(888999, (int) $v2->shopify_product_id);
    }

    public function test_procesar_producto_eliminado_no_encontrado_retorna_200(): void
    {
        $request = new Request([
            'id' => 777777,
        ]);

        $ref = new ReflectionClass($this->controller);
        $method = $ref->getMethod('procesarProductoEliminadoShopify');
        $method->setAccessible(true);

        $response = $method->invoke($this->controller, $request, $this->empresa);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('success', $data['status']);
        $this->assertSame(0, $data['productos_afectados']);
    }

    public function test_procesar_producto_eliminado_sin_id_retorna_ignored(): void
    {
        $request = new Request([]);

        $ref = new ReflectionClass($this->controller);
        $method = $ref->getMethod('procesarProductoEliminadoShopify');
        $method->setAccessible(true);

        $response = $method->invoke($this->controller, $request, $this->empresa);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('ignored', $data['status']);
    }
}
