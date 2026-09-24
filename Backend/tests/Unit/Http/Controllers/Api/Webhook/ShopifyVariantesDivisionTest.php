<?php

namespace Tests\Unit\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Api\Webhook\ShopifyController;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\User;
use App\Services\ImpuestosService;
use App\Services\ShippingService;
use App\Services\ShopifyImageService;
use App\Services\ShopifySyncCache;
use App\Services\ShopifyTransformer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class ShopifyVariantesDivisionTest extends TestCase
{
    private ShopifyController $controller;
    private Empresa $empresa;
    private User $usuario;

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
            $table->string('shopify_store_url')->nullable();
            $table->string('shopify_access_token')->nullable();
            $table->string('shopify_consumer_secret')->nullable();
            $table->string('shopify_status')->nullable();
            $table->text('custom_empresa')->nullable();
            $table->timestamps();
        });

        Schema::create('categorias', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('nombre')->nullable();
            $table->string('descripcion')->nullable();
            $table->string('enable')->default('1');
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
            $table->string('barcode')->nullable();
            $table->string('nombre')->nullable();
            $table->string('nombre_variante')->nullable();
            $table->string('option1_name')->nullable();
            $table->string('option1_value')->nullable();
            $table->string('option2_name')->nullable();
            $table->string('option2_value')->nullable();
            $table->string('option3_name')->nullable();
            $table->string('option3_value')->nullable();
            $table->text('descripcion')->nullable();
            $table->text('descripcion_completa')->nullable();
            $table->decimal('precio', 10, 2)->default(0);
            $table->decimal('precio_sin_iva', 10, 2)->default(0);
            $table->decimal('precio_con_iva', 10, 2)->default(0);
            $table->decimal('costo', 10, 2)->default(0);
            $table->string('tipo')->nullable();
            $table->boolean('syncing_from_shopify')->default(false);
            $table->timestamp('last_shopify_sync')->nullable();
            $table->string('enable')->default('1');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('inventario', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_producto');
            $table->unsignedBigInteger('id_bodega');
            $table->decimal('stock', 10, 2)->default(0);
            $table->decimal('stock_minimo', 10, 2)->default(0);
            $table->decimal('stock_maximo', 10, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('kardexs', function ($table) {
            $table->id();
            $table->date('fecha')->nullable();
            $table->unsignedBigInteger('id_producto')->nullable();
            $table->unsignedBigInteger('lote_id')->nullable();
            $table->unsignedBigInteger('id_inventario')->nullable();
            $table->string('detalle')->nullable();
            $table->string('referencia')->nullable();
            $table->decimal('entrada_cantidad', 10, 2)->nullable();
            $table->decimal('costo_unitario', 10, 2)->nullable();
            $table->decimal('entrada_valor', 10, 2)->nullable();
            $table->decimal('salida_cantidad', 10, 2)->nullable();
            $table->decimal('precio_unitario', 10, 2)->nullable();
            $table->decimal('salida_valor', 10, 2)->nullable();
            $table->decimal('total_cantidad', 10, 2)->default(0);
            $table->decimal('total_valor', 10, 2)->default(0);
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->timestamps();
        });

        Schema::create('sucursal_bodegas', function ($table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->timestamps();
        });

        Schema::create('shopify_locations', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('shopify_location_id')->nullable();
            $table->unsignedBigInteger('id_bodega')->nullable();
            $table->boolean('sincronizar_stock')->default(true);
            $table->timestamps();
        });

        $impuestos = $this->createMock(ImpuestosService::class);
        $impuestos->method('calcularPrecioSinImpuesto')->willReturnCallback(function ($precio) {
            return $precio;
        });

        $cache = $this->createMock(ShopifySyncCache::class);
        $cache->method('isLocked')->willReturn(true);

        $this->controller = new ShopifyController(
            new ShopifyTransformer($impuestos),
            $cache,
            $this->createMock(ShippingService::class),
            $impuestos,
            $this->createMock(ShopifyImageService::class)
        );

        $this->empresa = Empresa::create([
            'shopify_status' => 'connected',
            'shopify_sync_bidirectional' => false,
        ]);

        $this->usuario = new User();
        $this->usuario->id = 7;
        $this->usuario->id_bodega = 689;
        $this->usuario->id_sucursal = 1;
    }

    public function test_dividir_producto_simple_reparte_el_stock_en_las_variantes(): void
    {
        $original = Producto::create([
            'id_empresa' => $this->empresa->id,
            'nombre' => 'paquete de bienvenida plus',
            'codigo' => 'PACK',
            'shopify_sku' => 'PACK',
            'shopify_product_id' => 15207983808882,
            'shopify_variant_id' => 62398060462450,
            'shopify_inventory_item_id' => 64416486785394,
            'enable' => '1',
            'precio' => 10,
            'costo' => 5,
        ]);

        Inventario::create([
            'id_producto' => $original->id,
            'id_bodega' => 689,
            'stock' => 100,
        ]);

        $request = new Request([
            'id' => 15207983808882,
            'title' => 'paquete de bienvenida plus',
            'status' => 'active',
            'options' => [['name' => 'Talla']],
            'variants' => [
                [
                    'id' => 7001,
                    'title' => 'S',
                    'option1' => 'S',
                    'price' => '10.00',
                    'sku' => 'PACK',
                    'inventory_item_id' => 9001,
                    'inventory_quantity' => 40,
                ],
                [
                    'id' => 7002,
                    'title' => 'M',
                    'option1' => 'M',
                    'price' => '10.00',
                    'sku' => 'PACK',
                    'inventory_item_id' => 9002,
                    'inventory_quantity' => 60,
                ],
            ],
        ]);

        $response = $this->invocar('procesarProductoActualizado', [$request, $this->empresa, $this->usuario]);

        $this->assertSame(200, $response->getStatusCode());

        $original->refresh();
        $this->assertSame('0', (string) $original->enable);
        $this->assertNull($original->shopify_variant_id);
        $this->assertSame(15207983808882, (int) $original->shopify_product_id);
        $this->assertSame(0.0, (float) Inventario::where('id_producto', $original->id)->sum('stock'));

        $variantes = Producto::where('shopify_product_id', 15207983808882)
            ->where('enable', '!=', '0')
            ->orderBy('shopify_variant_id')
            ->get();

        $this->assertCount(2, $variantes);
        $this->assertSame(40.0, (float) Inventario::where('id_producto', $variantes[0]->id)->sum('stock'));
        $this->assertSame(60.0, (float) Inventario::where('id_producto', $variantes[1]->id)->sum('stock'));
        $this->assertNotSame($original->id, $variantes[0]->id);
    }

    public function test_inventario_pendiente_se_aplica_al_crear_la_variante(): void
    {
        Producto::create([
            'id_empresa' => $this->empresa->id,
            'nombre' => 'paquete de bienvenida plus',
            'codigo' => 'PACK',
            'shopify_sku' => 'PACK',
            'shopify_product_id' => 15207983808882,
            'shopify_variant_id' => 111,
            'shopify_inventory_item_id' => 222,
            'enable' => '1',
        ]);

        $aviso = new Request([
            'inventory_item_id' => 9001,
            'location_id' => 116424671602,
            'available' => 40,
        ]);
        $this->invocar('procesarInventarioActualizadoShopify', [$aviso, $this->empresa, $this->usuario]);

        $request = new Request([
            'id' => 15207983808882,
            'title' => 'paquete de bienvenida plus',
            'status' => 'active',
            'options' => [['name' => 'Talla']],
            'variants' => [
                [
                    'id' => 7001,
                    'title' => 'S',
                    'option1' => 'S',
                    'price' => '10.00',
                    'sku' => 'PACK',
                    'inventory_item_id' => 9001,
                    'inventory_quantity' => 0,
                ],
            ],
        ]);

        $this->invocar('procesarProductoActualizado', [$request, $this->empresa, $this->usuario]);

        $variante = Producto::where('shopify_variant_id', 7001)->first();
        $this->assertNotNull($variante);
        $this->assertSame(40.0, (float) Inventario::where('id_producto', $variante->id)->where('id_bodega', 689)->value('stock'));
    }

    private function invocar(string $metodo, array $args)
    {
        $ref = new ReflectionClass($this->controller);
        $method = $ref->getMethod($metodo);
        $method->setAccessible(true);

        return $method->invokeArgs($this->controller, $args);
    }
}
