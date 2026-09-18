<?php

namespace Tests\Unit\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Api\Webhook\ShopifyController;
use App\Models\Ventas\Venta;
use App\Services\ImpuestosService;
use App\Services\ShippingService;
use App\Services\ShopifyImageService;
use App\Services\ShopifySyncCache;
use App\Services\ShopifyTransformer;
use Illuminate\Http\Request;
use ReflectionClass;
use Tests\TestCase;

class ShopifyStockActualizarTest extends TestCase
{
    private ShopifyController $controller;
    private ShopifyTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        \Illuminate\Support\Facades\Schema::create('shopify_locations', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('shopify_location_id')->nullable();
            $table->unsignedBigInteger('id_sucursal')->nullable();
            $table->timestamps();
        });

        $impuestosService = $this->createMock(ImpuestosService::class);
        $this->transformer = new ShopifyTransformer($impuestosService);
        $cache = $this->createMock(ShopifySyncCache::class);
        $shippingService = $this->createMock(ShippingService::class);
        $imageService = $this->createMock(ShopifyImageService::class);

        $this->controller = new ShopifyController(
            $this->transformer,
            $cache,
            $shippingService,
            $impuestosService,
            $imageService
        );
    }

    public function test_transformar_producto_incluye_shopify_variant_id(): void
    {
        $shopifyData = [
            'id' => 11223344,
            'product_id' => 55667788,
            'variant_id' => 99887766,
            'title' => 'Producto Prueba',
            'sku' => 'SKU-PRUEBA-01',
            'price' => 15.50,
        ];

        $resultado = $this->transformer->transformarProducto($shopifyData, 1, 1, 1);

        $this->assertArrayHasKey('shopify_variant_id', $resultado);
        $this->assertSame(99887766, $resultado['shopify_variant_id']);
        $this->assertSame(55667788, $resultado['shopify_product_id']);
        $this->assertSame('SKU-PRUEBA-01', $resultado['codigo']);
    }

    public function test_actualizar_cantidades_productos_retorna_temprano_si_line_items_esta_vacio(): void
    {
        $reflector = new ReflectionClass(ShopifyController::class);
        $method = $reflector->getMethod('actualizarCantidadesProductos');
        $method->setAccessible(true);

        $venta = new Venta();
        $request = new Request(['line_items' => []]);
        $usuario = new \App\Models\User();

        // No debe lanzar excepciones ni consultar la BD de detalles
        $resultado = $method->invoke($this->controller, $venta, $request, $usuario);
        $this->assertNull($resultado);
    }

    public function test_transformar_producto_sin_variant_id_asigna_null(): void
    {
        $shopifyData = [
            'id' => 11223344,
            'title' => 'Producto Sin Variante',
            'price' => 10.00,
        ];

        $resultado = $this->transformer->transformarProducto($shopifyData, 1, 1, 1);

        $this->assertArrayHasKey('shopify_variant_id', $resultado);
        $this->assertNull($resultado['shopify_variant_id']);
        $this->assertNull($resultado['shopify_product_id']);
    }

    public function test_procesar_inventario_actualizado_retorna_ignorado_si_falta_inventory_item_id(): void
    {
        $reflector = new ReflectionClass(ShopifyController::class);
        $method = $reflector->getMethod('procesarInventarioActualizadoShopify');
        $method->setAccessible(true);

        $empresa = new \App\Models\Admin\Empresa();
        $usuario = new \App\Models\User();
        $request = new Request([]);

        $response = $method->invoke($this->controller, $request, $empresa, $usuario);
        $data = $response->getData(true);

        $this->assertSame('ignored', $data['status']);
        $this->assertSame('Missing inventory_item_id', $data['message']);
    }

    public function test_procesar_traslado_retorna_ignorado_si_falta_transfer_id(): void
    {
        $reflector = new ReflectionClass(ShopifyController::class);
        $method = $reflector->getMethod('procesarTrasladoInventarioShopify');
        $method->setAccessible(true);

        $empresa = new \App\Models\Admin\Empresa();
        $usuario = new \App\Models\User();
        $request = new Request([]);

        $response = $method->invoke($this->controller, $request, $empresa, $usuario);
        $data = $response->getData(true);

        $this->assertSame('ignored', $data['status']);
        $this->assertSame('Missing or invalid transfer id', $data['message']);
    }

    public function test_procesar_traslado_retorna_ignorado_si_faltan_ubicaciones(): void
    {
        $reflector = new ReflectionClass(ShopifyController::class);
        $method = $reflector->getMethod('procesarTrasladoInventarioShopify');
        $method->setAccessible(true);

        $empresa = new \App\Models\Admin\Empresa();
        $usuario = new \App\Models\User();
        $request = new Request(['id' => '123456789']);

        $response = $method->invoke($this->controller, $request, $empresa, $usuario);
        $data = $response->getData(true);

        $this->assertSame('ignored', $data['status']);
        $this->assertSame('Missing origin or destination location', $data['message']);
    }

    public function test_procesar_traslado_sin_destino_valida_mapeo_origen(): void
    {
        $reflector = new ReflectionClass(ShopifyController::class);
        $method = $reflector->getMethod('procesarTrasladoInventarioShopify');
        $method->setAccessible(true);

        $empresa = new \App\Models\Admin\Empresa();
        $usuario = new \App\Models\User();
        $request = new Request([
            'id' => '123456789',
            'origin' => ['id' => 'gid://shopify/Location/999999'],
        ]);

        $response = $method->invoke($this->controller, $request, $empresa, $usuario);
        $data = $response->getData(true);

        $this->assertSame('ignored', $data['status']);
        $this->assertSame('Ubicaciones no mapeadas a bodegas en SmartPyme', $data['message']);
    }

    public function test_procesar_traslado_sin_origen_valida_mapeo_destino(): void
    {
        $reflector = new ReflectionClass(ShopifyController::class);
        $method = $reflector->getMethod('procesarTrasladoInventarioShopify');
        $method->setAccessible(true);

        $empresa = new \App\Models\Admin\Empresa();
        $usuario = new \App\Models\User();
        $request = new Request([
            'id' => '123456789',
            'destination' => ['id' => 'gid://shopify/Location/888888'],
        ]);

        $response = $method->invoke($this->controller, $request, $empresa, $usuario);
        $data = $response->getData(true);

        $this->assertSame('ignored', $data['status']);
        $this->assertSame('Ubicaciones no mapeadas a bodegas en SmartPyme', $data['message']);
    }
}
