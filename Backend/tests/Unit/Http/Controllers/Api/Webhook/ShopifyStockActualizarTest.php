<?php

namespace Tests\Unit\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Api\Webhook\ShopifyController;
use App\Models\Admin\Empresa;
use App\Models\Admin\ShopifyLocation;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Kardex;
use App\Models\Inventario\Producto;
use App\Models\Ventas\Venta;
use App\Services\ImpuestosService;
use App\Services\ShippingService;
use App\Services\Shopify\ShopifyClienteService;
use App\Services\Shopify\ShopifyVentaService;
use App\Services\ShopifyImageService;
use App\Services\ShopifySyncCache;
use App\Services\ShopifyTransformer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
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
            $this->createMock(ShopifyVentaService::class),
            $this->createMock(ShopifyClienteService::class),
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

    public function test_sincronizar_stock_desde_shopify_multi_sucursal_existe_y_es_accesible(): void
    {
        $reflector = new ReflectionClass(ShopifyController::class);
        $this->assertTrue($reflector->hasMethod('sincronizarStockDesdeShopifyMultiSucursal'));

        $method = $reflector->getMethod('sincronizarStockDesdeShopifyMultiSucursal');
        $this->assertTrue($method->isPrivate());
    }

    public function test_actualizacion_de_producto_no_baja_el_stock_de_una_cotizacion(): void
    {
        Schema::table('shopify_locations', function ($table) {
            $table->unsignedBigInteger('id_bodega')->nullable();
            $table->boolean('sincronizar_stock')->default(false);
        });
        Schema::create('empresas', function ($table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->string('woocommerce_status')->nullable();
        });
        Schema::create('productos', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('nombre')->nullable();
            $table->decimal('precio', 10, 2)->default(0);
            $table->decimal('costo', 10, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('inventario', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_producto');
            $table->unsignedBigInteger('id_bodega');
            $table->decimal('stock', 10, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        $producto = Producto::create(['nombre' => 'goofy de peluche', 'id_empresa' => 1, 'precio' => 15]);
        Inventario::create(['id_producto' => $producto->id, 'id_bodega' => 689, 'stock' => 10]);
        Schema::create('ventas', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_bodega')->nullable();
            $table->unsignedTinyInteger('cotizacion')->default(0);
            $table->string('estado')->nullable();
        });
        Schema::create('detalles_venta', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_venta')->nullable();
            $table->unsignedBigInteger('id_producto')->nullable();
            $table->decimal('cantidad', 10, 2)->default(0);
        });
        $ventaId = \Illuminate\Support\Facades\DB::table('ventas')->insertGetId([
            'id_empresa' => 1,
            'id_bodega' => 689,
            'cotizacion' => 1,
            'estado' => 'Pendiente',
        ]);
        \Illuminate\Support\Facades\DB::table('detalles_venta')->insert([
            'id_venta' => $ventaId,
            'id_producto' => $producto->id,
            'cantidad' => 3,
        ]);
        ShopifyLocation::create([
            'id_empresa' => 1,
            'shopify_location_id' => 116424671602,
            'id_bodega' => 689,
            'sincronizar_stock' => true,
        ]);

        $empresa = new Empresa();
        $empresa->id = 1;
        $empresa->shopify_store_url = 'https://tienda.myshopify.com';
        $empresa->shopify_consumer_secret = 'shpat_test';

        Http::fake([
            'https://tienda.myshopify.com/admin/api/2024-01/inventory_levels.json*' => Http::response([
                'inventory_levels' => [[
                    'location_id' => 116424671602,
                    'available' => 15,
                ]],
            ], 200),
        ]);

        $usuario = new \App\Models\User();
        $usuario->id = 1;

        $method = (new ReflectionClass(ShopifyController::class))->getMethod('sincronizarStockDesdeShopifyMultiSucursal');
        $method->setAccessible(true);
        $method->invoke($this->controller, $producto, 64442868662642, $empresa, $usuario, 0, 'actualizacion_shopify');

        $this->assertEquals(10, (float) Inventario::where('id_producto', $producto->id)->where('id_bodega', 689)->value('stock'));
    }

    public function test_baja_de_inventario_shopify_no_revienta_y_no_descuenta(): void
    {
        Schema::create('empresas', function ($table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->string('woocommerce_status')->nullable();
        });
        Schema::create('productos', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('nombre')->nullable();
            $table->unsignedBigInteger('shopify_inventory_item_id')->nullable();
            $table->decimal('precio', 10, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('inventario', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_producto');
            $table->unsignedBigInteger('id_bodega');
            $table->decimal('stock', 10, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('ventas', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_bodega')->nullable();
            $table->unsignedTinyInteger('cotizacion')->default(0);
            $table->string('estado')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('detalles_venta', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_venta')->nullable();
            $table->unsignedBigInteger('id_producto')->nullable();
            $table->decimal('cantidad', 10, 2)->default(0);
        });

        $producto = Producto::create([
            'nombre' => 'goofy de peluche',
            'id_empresa' => 1,
            'shopify_inventory_item_id' => 64442868662642,
        ]);
        Inventario::create(['id_producto' => $producto->id, 'id_bodega' => 689, 'stock' => 981]);
        $ventaId = \Illuminate\Support\Facades\DB::table('ventas')->insertGetId([
            'id_empresa' => 1,
            'id_bodega' => 689,
            'cotizacion' => 1,
            'estado' => 'Pendiente',
        ]);
        \Illuminate\Support\Facades\DB::table('detalles_venta')->insert([
            'id_venta' => $ventaId,
            'id_producto' => $producto->id,
            'cantidad' => 3,
        ]);

        $empresa = new Empresa();
        $empresa->id = 1;
        $empresa->shopify_store_url = 'https://tienda.myshopify.com';
        $empresa->shopify_consumer_secret = 'shpat_test';
        $usuario = new \App\Models\User();
        $usuario->id = 1;
        $usuario->id_bodega = 689;

        Http::fake([
            'https://tienda.myshopify.com/admin/api/2026-10/graphql.json' => Http::response([
                'data' => ['inventoryItem' => ['inventoryLevel' => ['quantities' => [
                    ['name' => 'on_hand', 'quantity' => 981],
                    ['name' => 'available', 'quantity' => 978],
                    ['name' => 'committed', 'quantity' => 3],
                ]]]],
            ], 200),
        ]);

        $request = new Request([
            'inventory_item_id' => 64442868662642,
            'location_id' => 116424671602,
            'available' => 978,
        ]);

        $method = (new ReflectionClass(ShopifyController::class))->getMethod('procesarInventarioActualizadoShopify');
        $method->setAccessible(true);
        $response = $method->invoke($this->controller, $request, $empresa, $usuario);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('success', $response->getData(true)['status']);
        $this->assertEquals(981, (float) Inventario::where('id_producto', $producto->id)->value('stock'));
    }

    public function test_ajuste_manual_de_shopify_baja_el_stock_si_no_hay_cotizacion(): void
    {
        Schema::create('empresas', function ($table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->string('woocommerce_status')->nullable();
        });
        Schema::create('productos', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('nombre')->nullable();
            $table->unsignedBigInteger('shopify_inventory_item_id')->nullable();
            $table->decimal('precio', 10, 2)->default(0);
            $table->decimal('costo', 10, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('inventario', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_producto');
            $table->unsignedBigInteger('id_bodega');
            $table->decimal('stock', 10, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('ventas', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_bodega')->nullable();
            $table->unsignedTinyInteger('cotizacion')->default(0);
            $table->string('estado')->nullable();
        });
        Schema::create('detalles_venta', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_venta')->nullable();
            $table->unsignedBigInteger('id_producto')->nullable();
            $table->decimal('cantidad', 10, 2)->default(0);
        });
        Schema::create('sucursal_bodegas', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
        });
        Schema::create('kardexs', function ($table) {
            $table->id();
            $table->date('fecha')->nullable();
            $table->unsignedBigInteger('id_producto')->nullable();
            $table->unsignedBigInteger('lote_id')->nullable();
            $table->unsignedBigInteger('id_inventario')->nullable();
            $table->string('detalle')->nullable();
            $table->string('referencia')->nullable();
            $table->decimal('precio_unitario', 10, 2)->nullable();
            $table->decimal('costo_unitario', 10, 2)->nullable();
            $table->decimal('entrada_cantidad', 10, 2)->nullable();
            $table->decimal('entrada_valor', 10, 2)->nullable();
            $table->decimal('salida_cantidad', 10, 2)->nullable();
            $table->decimal('salida_valor', 10, 2)->nullable();
            $table->decimal('total_cantidad', 10, 2)->nullable();
            $table->decimal('total_valor', 10, 2)->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->timestamps();
        });

        $producto = Producto::create([
            'nombre' => 'soda de prueba sin azucar',
            'id_empresa' => 1,
            'shopify_inventory_item_id' => 64416012992882,
            'precio' => 1,
            'costo' => 1,
        ]);
        Inventario::create(['id_producto' => $producto->id, 'id_bodega' => 1074, 'stock' => 11]);

        $empresa = new Empresa();
        $empresa->id = 1;
        $empresa->shopify_store_url = 'https://tienda.myshopify.com';
        $empresa->shopify_consumer_secret = 'shpat_test';
        $usuario = new \App\Models\User();
        $usuario->id = 1;
        $usuario->id_bodega = 1074;

        \Illuminate\Support\Facades\Cache::put('sync_lock_1', true, 120);
        Http::fake([
            'https://tienda.myshopify.com/admin/api/2026-10/graphql.json' => Http::response([
                'data' => ['inventoryItem' => ['inventoryLevel' => ['quantities' => [
                    ['name' => 'on_hand', 'quantity' => 9],
                    ['name' => 'available', 'quantity' => 9],
                    ['name' => 'committed', 'quantity' => 0],
                ]]]],
            ], 200),
        ]);

        $request = new Request([
            'inventory_item_id' => 64416012992882,
            'location_id' => 116936409458,
            'available' => 9,
        ]);

        $method = (new ReflectionClass(ShopifyController::class))->getMethod('procesarInventarioActualizadoShopify');
        $method->setAccessible(true);
        $response = $method->invoke($this->controller, $request, $empresa, $usuario);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('success', $response->getData(true)['status']);
        $this->assertEquals(9, (float) Inventario::where('id_producto', $producto->id)->where('id_bodega', 1074)->value('stock'));
    }

    public function test_pedido_no_pisa_el_stock_ya_descontado(): void
    {
        Schema::create('empresas', function ($table) {
            $table->id();
            $table->string('nombre')->nullable();
        });
        Schema::create('productos', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('nombre')->nullable();
            $table->unsignedBigInteger('shopify_inventory_item_id')->nullable();
            $table->decimal('precio', 10, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('inventario', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_producto');
            $table->unsignedBigInteger('id_bodega');
            $table->decimal('stock', 10, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        $producto = Producto::create([
            'nombre' => 'calcetas',
            'id_empresa' => 1,
            'shopify_inventory_item_id' => 64525558317426,
        ]);
        Inventario::create(['id_producto' => $producto->id, 'id_bodega' => 689, 'stock' => 99]);
        Cache::put('shopify_qty_1_64525558317426_689', [
            'on_hand' => 100,
            'available' => 100,
            'committed' => 0,
        ], 60);

        $empresa = new Empresa();
        $empresa->id = 1;
        $empresa->shopify_store_url = 'https://tienda.myshopify.com';
        $empresa->shopify_consumer_secret = 'shpat_test';
        $usuario = new \App\Models\User();
        $usuario->id = 1;
        $usuario->id_bodega = 689;

        Http::fake([
            'https://tienda.myshopify.com/admin/api/2026-10/graphql.json' => Http::response([
                'data' => ['inventoryItem' => ['inventoryLevel' => ['quantities' => [
                    ['name' => 'on_hand', 'quantity' => 100],
                    ['name' => 'available', 'quantity' => 99],
                    ['name' => 'committed', 'quantity' => 1],
                ]]]],
            ], 200),
        ]);

        $request = new Request([
            'inventory_item_id' => 64525558317426,
            'location_id' => 116424671602,
            'available' => 99,
        ]);

        $method = (new ReflectionClass(ShopifyController::class))->getMethod('procesarInventarioActualizadoShopify');
        $method->setAccessible(true);
        $response = $method->invoke($this->controller, $request, $empresa, $usuario);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertEquals(99, (float) Inventario::where('id_producto', $producto->id)->value('stock'));
    }

    public function test_consolidacion_individual_registra_el_ajuste_en_kardex(): void
    {
        Schema::create('empresas', function ($table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->string('shopify_store_url')->nullable();
            $table->string('shopify_consumer_secret')->nullable();
            $table->text('custom_empresa')->nullable();
            $table->timestamps();
        });
        Schema::create('productos', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('nombre')->nullable();
            $table->string('codigo')->nullable();
            $table->unsignedBigInteger('shopify_product_id')->nullable();
            $table->unsignedBigInteger('shopify_variant_id')->nullable();
            $table->unsignedBigInteger('shopify_inventory_item_id')->nullable();
            $table->string('shopify_sku')->nullable();
            $table->string('barcode')->nullable();
            $table->decimal('precio', 10, 2)->default(0);
            $table->decimal('precio_sin_iva', 10, 2)->default(0);
            $table->decimal('precio_con_iva', 10, 2)->default(0);
            $table->decimal('costo', 10, 2)->default(0);
            $table->boolean('inventario_por_lotes')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('inventario', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_producto');
            $table->unsignedBigInteger('id_bodega');
            $table->decimal('stock', 10, 2)->default(0);
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
            $table->decimal('precio_unitario', 10, 2)->nullable();
            $table->decimal('costo_unitario', 10, 2)->nullable();
            $table->decimal('entrada_cantidad', 10, 2)->nullable();
            $table->decimal('entrada_valor', 10, 2)->nullable();
            $table->decimal('salida_cantidad', 10, 2)->nullable();
            $table->decimal('salida_valor', 10, 2)->nullable();
            $table->decimal('total_cantidad', 10, 2)->nullable();
            $table->decimal('total_valor', 10, 2)->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->timestamps();
        });
        Schema::table('shopify_locations', function ($table) {
            $table->string('shopify_location_name')->nullable();
            $table->unsignedBigInteger('id_bodega')->nullable();
            $table->boolean('sincronizar_stock')->default(false);
        });

        $empresa = Empresa::create([
            'nombre' => 'Prueba',
            'shopify_store_url' => 'https://tienda.myshopify.com',
            'shopify_consumer_secret' => 'shpat_test',
        ]);
        $producto = Producto::create([
            'nombre' => 'calcetas invisibles',
            'id_empresa' => $empresa->id,
            'shopify_variant_id' => 62503936131442,
            'shopify_inventory_item_id' => 64525558317426,
            'precio' => 8.85,
            'costo' => 1,
        ]);
        Inventario::create(['id_producto' => $producto->id, 'id_bodega' => 689, 'stock' => 93]);
        ShopifyLocation::create([
            'id_empresa' => $empresa->id,
            'shopify_location_id' => 116424671602,
            'shopify_location_name' => 'Bodega',
            'id_bodega' => 689,
            'sincronizar_stock' => true,
        ]);

        $usuario = new \App\Models\User();
        $usuario->id = 1;
        $usuario->id_empresa = $empresa->id;
        Auth::setUser($usuario);

        Http::fake([
            'https://tienda.myshopify.com/admin/api/2024-01/*' => function ($request) {
                if (str_contains($request->url(), 'inventory_levels')) {
                    return Http::response([
                        'inventory_levels' => [[
                            'inventory_item_id' => 64525558317426,
                            'location_id' => 116424671602,
                            'available' => 89,
                        ]],
                    ], 200);
                }

                return Http::response([
                    'variant' => [
                        'id' => 62503936131442,
                        'product_id' => 15207835795826,
                        'inventory_item_id' => 64525558317426,
                        'sku' => 'DMHM20323',
                        'price' => '10.00',
                    ],
                ], 200);
            },
        ]);

        $response = $this->controller->sincronizarProductoIndividual($producto->id, new Request([
            'direccion' => 'shopify_to_sp',
            'sincronizar_precio' => false,
            'sincronizar_stock' => true,
            'sincronizar_imagenes' => false,
            'id_bodega' => 'todas',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertEquals(89, (float) Inventario::where('id_producto', $producto->id)->where('id_bodega', 689)->value('stock'));
        $kardex = Kardex::where('id_producto', $producto->id)->first();
        $this->assertNotNull($kardex);
        $this->assertSame('Ajuste de inventario desde Shopify', $kardex->detalle);
        $this->assertSame(1, (int) $kardex->id_usuario);
        $this->assertEquals(4, (float) $kardex->salida_cantidad);
        $this->assertEquals(89, (float) $kardex->total_cantidad);
    }
}
