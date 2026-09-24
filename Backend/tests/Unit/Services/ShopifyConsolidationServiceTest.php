<?php

namespace Tests\Unit\Services;

use App\Jobs\ConsolidarProductosShopifyJob;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Producto;
use App\Models\User;
use App\Services\ImpuestosService;
use App\Services\ShopifyApiClient;
use App\Services\ShopifyConsolidationService;
use App\Services\ShopifyTransformer;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShopifyConsolidationServiceTest extends TestCase
{
    private ShopifyTransformer $transformer;
    private Empresa $empresa;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        Schema::create('empresas', function ($table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->string('shopify_store_url')->nullable();
            $table->string('shopify_consumer_secret')->nullable();
            $table->string('shopify_app_id')->nullable();
            $table->string('shopify_client_id')->nullable();
            $table->string('shopify_client_secret')->nullable();
            $table->string('status_conexion_shopify')->nullable();
            $table->unsignedBigInteger('shopify_location_id')->nullable();
            $table->timestamp('shopify_last_sync')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_sucursal')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('categorias', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('nombre')->nullable();
            $table->boolean('enable')->default(true);
            $table->text('descripcion')->nullable();
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
            $table->string('shopify_variant_image_id')->nullable();
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
            $table->string('tipo')->default('Producto');
            $table->boolean('enable')->default(true);
            $table->boolean('syncing_from_shopify')->default(false);
            $table->timestamp('last_shopify_sync')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('inventario', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_producto');
            $table->unsignedBigInteger('id_bodega')->default(1);
            $table->decimal('stock', 12, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('detalles_venta', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_venta')->nullable();
            $table->unsignedBigInteger('id_producto');
            $table->decimal('cantidad', 12, 2)->default(1);
            $table->timestamps();
        });

        $this->empresa = Empresa::forceCreate([
            'id' => 1,
            'nombre' => 'Test Company',
            'shopify_store_url' => 'https://test-shop.myshopify.com',
            'shopify_consumer_secret' => 'shpat_1234567890',
            'status_conexion_shopify' => 'connected',
            'shopify_location_id' => 99999,
        ]);

        $this->user = User::forceCreate([
            'id' => 1,
            'id_empresa' => 1,
            'id_sucursal' => 1,
            'name' => 'Admin User',
            'email' => 'admin@test.com',
        ]);

        $impuestosService = $this->createMock(ImpuestosService::class);
        $impuestosService->method('calcularPrecioSinImpuesto')->willReturnCallback(function ($precio) {
            return (float) $precio;
        });
        $this->transformer = new ShopifyTransformer($impuestosService);
    }

    public function test_transformer_construir_nombre_variante(): void
    {
        $variantConOpciones = [
            'option1' => 'Rojo',
            'option2' => 'XL',
            'option3' => 'Algodón',
        ];
        $nombre = $this->transformer->construirNombreVariante($variantConOpciones);
        $this->assertEquals('Rojo - XL - Algodón', $nombre);

        $variantDefault = [
            'title' => 'Default Title',
            'option1' => 'Default Title',
        ];
        $nombreDefault = $this->transformer->construirNombreVariante($variantDefault);
        $this->assertNull($nombreDefault);

        // Variante con una sola opción
        $variantUnaOpcion = [
            'option1' => 'Azul',
        ];
        $nombreUnaOpcion = $this->transformer->construirNombreVariante($variantUnaOpcion);
        $this->assertEquals('Azul', $nombreUnaOpcion);
    }

    public function test_controlar_rate_limit_con_consumo_bajo_no_bloquea(): void
    {
        $service = new ShopifyConsolidationService($this->transformer);

        $psrResponse = new Psr7Response(200, [
            'X-Shopify-Shop-Api-Call-Limit' => ['10/40']
        ], '{}');
        $response = new Response($psrResponse);

        $start = microtime(true);
        $service->controlarRateLimit($response);
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(0.1, $elapsed);
    }

    public function test_controlar_rate_limit_con_consumo_alto_aplica_demora(): void
    {
        $service = new ShopifyConsolidationService($this->transformer);

        $psrResponse = new Psr7Response(200, [
            'X-Shopify-Shop-Api-Call-Limit' => ['38/40']
        ], '{}');
        $response = new Response($psrResponse);

        $start = microtime(true);
        $service->controlarRateLimit($response);
        $elapsed = microtime(true) - $start;

        $this->assertGreaterThanOrEqual(0.45, $elapsed);
    }

    public function test_controlar_rate_limit_con_429_respeta_retry_after(): void
    {
        $service = new ShopifyConsolidationService($this->transformer);

        $psrResponse = new Psr7Response(429, [
            'Retry-After' => ['1']
        ], '{"errors": "Exceeded 2 calls per second for api client."}');
        $response = new Response($psrResponse);

        $start = microtime(true);
        $service->controlarRateLimit($response);
        $elapsed = microtime(true) - $start;

        $this->assertGreaterThanOrEqual(0.95, $elapsed);
    }

    public function test_consolidar_shopify_hacia_smartpyme_vincula_por_sku_sin_duplicar(): void
    {
        $productoExistente = Producto::forceCreate([
            'id_empresa' => 1,
            'codigo' => 'POLO-BLANCO-M',
            'nombre' => 'Polo Blanco',
            'precio' => 15.00,
            'precio_sin_iva' => 13.27,
            'precio_con_iva' => 15.00,
            'costo' => 8.00,
            'enable' => true,
            'shopify_product_id' => null,
            'shopify_variant_id' => null,
            'shopify_inventory_item_id' => null,
        ]);

        $shopifyProducts = [
            [
                'id' => 8888001,
                'title' => 'Polo Blanco Colección 2026',
                'status' => 'active',
                'variants' => [
                    [
                        'id' => 9999001,
                        'product_id' => 8888001,
                        'title' => 'M',
                        'sku' => 'POLO-BLANCO-M',
                        'price' => '18.50',
                        'inventory_item_id' => 7777001,
                        'inventory_quantity' => 25,
                        'option1' => 'M',
                    ]
                ],
                'options' => [
                    ['name' => 'Talla']
                ]
            ]
        ];

        $mockClient = $this->createMock(ShopifyApiClient::class);
        $service = $this->getMockBuilder(ShopifyConsolidationService::class)
            ->setConstructorArgs([$this->transformer, $mockClient])
            ->onlyMethods(['obtenerTodosProductosShopify'])
            ->getMock();

        $service->expects($this->once())
            ->method('obtenerTodosProductosShopify')
            ->willReturn($shopifyProducts);

        $opciones = [
            'vincular_sku' => true,
            'actualizar_precios' => true,
            'actualizar_stock' => false,
            'crear_nuevos' => true,
        ];

        $metricas = $service->consolidarShopifyHaciaSmartpyme($this->empresa, $this->user, $opciones);

        $this->assertEquals(1, $metricas['total']);
        $this->assertEquals(1, $metricas['procesados']);
        $this->assertEquals(1, $metricas['vinculados']);
        $this->assertEquals(0, $metricas['creados']);
        $this->assertEquals(0, $metricas['errores']);

        $this->assertEquals(1, Producto::count());

        $productoActualizado = Producto::find($productoExistente->id);
        $this->assertEquals(8888001, $productoActualizado->shopify_product_id);
        $this->assertEquals(9999001, $productoActualizado->shopify_variant_id);
        $this->assertEquals(7777001, $productoActualizado->shopify_inventory_item_id);
        $this->assertEquals('M', $productoActualizado->nombre_variante);
        $this->assertEquals(18.50, (float) $productoActualizado->precio);
    }

    public function test_consolidar_shopify_hacia_smartpyme_crea_variante_faltante(): void
    {
        $this->assertEquals(0, Producto::count());

        $shopifyProducts = [
            [
                'id' => 5001,
                'title' => 'Gorra Trucker',
                'status' => 'active',
                'variants' => [
                    [
                        'id' => 6001,
                        'product_id' => 5001,
                        'title' => 'Negra',
                        'sku' => 'GORRA-TRK-NEG',
                        'price' => '12.00',
                        'inventory_item_id' => 7001,
                        'inventory_quantity' => 10,
                        'option1' => 'Negra',
                    ]
                ],
                'options' => [
                    ['name' => 'Color']
                ]
            ]
        ];

        $mockClient = $this->createMock(ShopifyApiClient::class);
        $service = $this->getMockBuilder(ShopifyConsolidationService::class)
            ->setConstructorArgs([$this->transformer, $mockClient])
            ->onlyMethods(['obtenerTodosProductosShopify'])
            ->getMock();

        $service->expects($this->once())
            ->method('obtenerTodosProductosShopify')
            ->willReturn($shopifyProducts);

        $opciones = [
            'vincular_sku' => true,
            'actualizar_precios' => true,
            'actualizar_stock' => false,
            'crear_nuevos' => true,
        ];

        $metricas = $service->consolidarShopifyHaciaSmartpyme($this->empresa, $this->user, $opciones);

        $this->assertEquals(1, $metricas['creados']);
        $this->assertEquals(1, Producto::count());

        $nuevo = Producto::first();
        $this->assertEquals('Gorra Trucker', $nuevo->nombre);
        $this->assertEquals('Negra', $nuevo->nombre_variante);
        $this->assertEquals('GORRA-TRK-NEG', $nuevo->codigo);
        $this->assertEquals(5001, $nuevo->shopify_product_id);
        $this->assertEquals(6001, $nuevo->shopify_variant_id);
        $this->assertEquals(7001, $nuevo->shopify_inventory_item_id);
    }

    public function test_consolidar_smartpyme_hacia_shopify_vincula_por_sku(): void
    {
        $producto = Producto::forceCreate([
            'id_empresa' => 1,
            'codigo' => 'ZAPATO-OXFORD-42',
            'nombre' => 'Zapato Oxford Clásico',
            'precio' => 60.00,
            'precio_sin_iva' => 53.10,
            'precio_con_iva' => 60.00,
            'costo' => 30.00,
            'enable' => true,
            'shopify_product_id' => null,
            'shopify_variant_id' => null,
        ]);

        $shopifyProducts = [
            [
                'id' => 333001,
                'title' => 'Zapato Oxford Clásico',
                'variants' => [
                    [
                        'id' => 444001,
                        'product_id' => 333001,
                        'title' => '42',
                        'sku' => 'ZAPATO-OXFORD-42',
                        'price' => '60.00',
                        'inventory_item_id' => 555001,
                    ]
                ]
            ]
        ];

        $mockClient = $this->createMock(ShopifyApiClient::class);
        $service = $this->getMockBuilder(ShopifyConsolidationService::class)
            ->setConstructorArgs([$this->transformer, $mockClient])
            ->onlyMethods(['obtenerTodosProductosShopify'])
            ->getMock();

        $service->expects($this->once())
            ->method('obtenerTodosProductosShopify')
            ->willReturn($shopifyProducts);

        $opciones = [
            'vincular_sku' => true,
            'actualizar_precios' => false,
            'actualizar_stock' => false,
            'crear_nuevos' => false,
        ];

        $metricas = $service->consolidarSmartpymeHaciaShopify($this->empresa, $this->user, $opciones);

        $this->assertEquals(1, $metricas['vinculados']);
        $this->assertEquals(0, $metricas['creados']);

        $productoActualizado = Producto::find($producto->id);
        $this->assertEquals(333001, $productoActualizado->shopify_product_id);
        $this->assertEquals(444001, $productoActualizado->shopify_variant_id);
        $this->assertEquals(555001, $productoActualizado->shopify_inventory_item_id);
    }

    public function test_consolidar_productos_shopify_job_ejecucion_y_cache(): void
    {
        $cacheKey = "shopify_consolidacion_{$this->empresa->id}";
        Cache::forget($cacheKey);

        $mockService = $this->createMock(ShopifyConsolidationService::class);
        $mockService->expects($this->once())
            ->method('consolidarShopifyHaciaSmartpyme')
            ->willReturn([
                'total' => 10,
                'procesados' => 10,
                'vinculados' => 5,
                'actualizados' => 3,
                'creados' => 2,
                'errores' => 0,
            ]);

        $job = new ConsolidarProductosShopifyJob($this->empresa->id, $this->user->id, 'shopify_to_sp', []);
        $job->handle($mockService);

        $estadoFinal = Cache::get($cacheKey);
        $this->assertNotNull($estadoFinal);
        $this->assertEquals('completado', $estadoFinal['estado']);
        $this->assertEquals(100, $estadoFinal['progreso']);
        $this->assertEquals(5, $estadoFinal['vinculados']);
        $this->assertEquals(3, $estadoFinal['actualizados']);
        $this->assertEquals(2, $estadoFinal['creados']);
        $this->assertEquals(0, $estadoFinal['errores']);
    }

    public function test_obtener_todos_productos_con_array_response_de_api_client(): void
    {
        $mockClient = $this->createMock(ShopifyApiClient::class);
        $mockClient->expects($this->once())
            ->method('get')
            ->willReturn([
                'status' => 'success',
                'body' => [
                    'products' => [
                        ['id' => 101, 'title' => 'Test Product', 'variants' => []]
                    ]
                ],
                'headers' => [],
                'link' => null,
                'call_limit' => '10/40',
                'http_status' => 200,
            ]);

        $service = new ShopifyConsolidationService($this->transformer);
        $products = $service->obtenerTodosProductosShopify($mockClient);

        $this->assertCount(1, $products);
        $this->assertEquals(101, $products[0]['id']);
    }

    public function test_consolidar_smartpyme_hacia_shopify_agrupa_variantes_en_un_solo_producto(): void
    {
        $prod1 = Producto::forceCreate([
            'id_empresa' => 1,
            'nombre' => 'Camisa Lino',
            'nombre_variante' => 'S',
            'option1_name' => 'Talla',
            'option1_value' => 'S',
            'codigo' => 'CAM-LINO-S',
            'precio' => 25.00,
            'precio_sin_iva' => 25.00,
            'precio_con_iva' => 28.25,
            'enable' => true,
        ]);

        $prod2 = Producto::forceCreate([
            'id_empresa' => 1,
            'nombre' => 'Camisa Lino',
            'nombre_variante' => 'M',
            'option1_name' => 'Talla',
            'option1_value' => 'M',
            'codigo' => 'CAM-LINO-M',
            'precio' => 25.00,
            'precio_sin_iva' => 25.00,
            'precio_con_iva' => 28.25,
            'enable' => true,
        ]);

        $mockClient = $this->createMock(ShopifyApiClient::class);
        $mockClient->expects($this->once())
            ->method('post')
            ->with(
                'products.json',
                $this->callback(function ($payload) {
                    $prod = $payload['product'] ?? [];
                    return ($prod['title'] ?? '') === 'Camisa Lino'
                        && count($prod['options'] ?? []) === 1
                        && ($prod['options'][0]['name'] ?? '') === 'Talla'
                        && count($prod['variants'] ?? []) === 2
                        && ($prod['variants'][0]['price'] ?? '') === '28.25'
                        && ($prod['variants'][0]['option1'] ?? '') === 'S'
                        && ($prod['variants'][1]['option1'] ?? '') === 'M';
                })
            )
            ->willReturn([
                'status' => 'success',
                'body' => [
                    'product' => [
                        'id' => 777001,
                        'title' => 'Camisa Lino',
                        'variants' => [
                            [
                                'id' => 888001,
                                'sku' => 'CAM-LINO-S',
                                'inventory_item_id' => 999001,
                            ],
                            [
                                'id' => 888002,
                                'sku' => 'CAM-LINO-M',
                                'inventory_item_id' => 999002,
                            ],
                        ],
                    ],
                ],
            ]);

        $service = $this->getMockBuilder(ShopifyConsolidationService::class)
            ->setConstructorArgs([$this->transformer, $mockClient])
            ->onlyMethods(['obtenerTodosProductosShopify'])
            ->getMock();

        $service->method('obtenerTodosProductosShopify')->willReturn([]);

        $metricas = $service->consolidarSmartpymeHaciaShopify($this->empresa, $this->user, [
            'vincular_sku' => true,
            'actualizar_precios' => true,
            'actualizar_stock' => false,
            'crear_nuevos' => true,
        ]);

        $this->assertEquals(2, $metricas['total']);
        $this->assertEquals(2, $metricas['creados']);
        $this->assertEquals(0, $metricas['errores']);

        $p1 = Producto::find($prod1->id);
        $p2 = Producto::find($prod2->id);

        $this->assertEquals(777001, $p1->shopify_product_id);
        $this->assertEquals(888001, $p1->shopify_variant_id);
        $this->assertEquals(999001, $p1->shopify_inventory_item_id);

        $this->assertEquals(777001, $p2->shopify_product_id);
        $this->assertEquals(888002, $p2->shopify_variant_id);
        $this->assertEquals(999002, $p2->shopify_inventory_item_id);
    }

    public function test_consolidar_smartpyme_hacia_shopify_agrega_variante_a_producto_existente(): void
    {
        // Variante 1 ya sincronizada en Shopify
        Producto::forceCreate([
            'id_empresa' => 1,
            'nombre' => 'Pantalón Chino',
            'nombre_variante' => '30',
            'option1_name' => 'Talla',
            'option1_value' => '30',
            'codigo' => 'PANT-CHINO-30',
            'precio' => 35.00,
            'precio_con_iva' => 39.55,
            'enable' => true,
            'shopify_product_id' => 666001,
            'shopify_variant_id' => 777001,
        ]);

        // Variante 2 nueva, sin shopify_variant_id
        $prod2 = Producto::forceCreate([
            'id_empresa' => 1,
            'nombre' => 'Pantalón Chino',
            'nombre_variante' => '32',
            'option1_name' => 'Talla',
            'option1_value' => '32',
            'codigo' => 'PANT-CHINO-32',
            'precio' => 35.00,
            'precio_con_iva' => 39.55,
            'enable' => true,
            'shopify_product_id' => null,
            'shopify_variant_id' => null,
        ]);

        $mockClient = $this->createMock(ShopifyApiClient::class);
        $mockClient->expects($this->once())
            ->method('post')
            ->with(
                'products/666001/variants.json',
                $this->callback(function ($payload) {
                    $v = $payload['variant'] ?? [];
                    return ($v['sku'] ?? '') === 'PANT-CHINO-32'
                        && ($v['option1'] ?? '') === '32'
                        && ($v['price'] ?? '') === '39.55';
                })
            )
            ->willReturn([
                'status' => 'success',
                'body' => [
                    'variant' => [
                        'id' => 777002,
                        'product_id' => 666001,
                        'sku' => 'PANT-CHINO-32',
                        'inventory_item_id' => 888002,
                    ]
                ]
            ]);

        $service = $this->getMockBuilder(ShopifyConsolidationService::class)
            ->setConstructorArgs([$this->transformer, $mockClient])
            ->onlyMethods(['obtenerTodosProductosShopify'])
            ->getMock();

        $service->method('obtenerTodosProductosShopify')->willReturn([]);

        $metricas = $service->consolidarSmartpymeHaciaShopify($this->empresa, $this->user, [
            'vincular_sku' => false,
            'actualizar_precios' => false,
            'actualizar_stock' => false,
            'crear_nuevos' => true,
        ]);

        $this->assertEquals(1, $metricas['creados']);
        $p2 = Producto::find($prod2->id);
        $this->assertEquals(666001, $p2->shopify_product_id);
        $this->assertEquals(777002, $p2->shopify_variant_id);
        $this->assertEquals(888002, $p2->shopify_inventory_item_id);
    }

    public function test_consolidar_smartpyme_hacia_shopify_actualiza_precios_existentes_con_iva(): void
    {
        Producto::forceCreate([
            'id_empresa' => 1,
            'nombre' => 'Cinturón Cuero',
            'codigo' => 'CINT-CUERO-01',
            'precio' => 18.00,
            'precio_sin_iva' => 18.00,
            'precio_con_iva' => 20.34,
            'enable' => true,
            'shopify_product_id' => 555001,
            'shopify_variant_id' => 444001,
        ]);

        $mockClient = $this->createMock(ShopifyApiClient::class);
        $mockClient->expects($this->once())
            ->method('put')
            ->with(
                'variants/444001.json',
                $this->callback(function ($payload) {
                    $v = $payload['variant'] ?? [];
                    return ($v['price'] ?? '') === '20.34'
                        && ($v['sku'] ?? '') === 'CINT-CUERO-01';
                })
            )
            ->willReturn([
                'status' => 'success',
                'body' => ['variant' => ['id' => 444001, 'price' => '20.34']]
            ]);

        $service = $this->getMockBuilder(ShopifyConsolidationService::class)
            ->setConstructorArgs([$this->transformer, $mockClient])
            ->onlyMethods(['obtenerTodosProductosShopify'])
            ->getMock();

        $service->method('obtenerTodosProductosShopify')->willReturn([]);

        $metricas = $service->consolidarSmartpymeHaciaShopify($this->empresa, $this->user, [
            'vincular_sku' => false,
            'actualizar_precios' => true,
            'actualizar_stock' => false,
            'crear_nuevos' => false,
        ]);

        $this->assertEquals(1, $metricas['actualizados']);
        $this->assertEquals(0, $metricas['errores']);
    }

    public function test_deduplicar_productos_locales_fusiona_inventario_e_inactiva_duplicado(): void
    {
        // 1. Producto canónico (tiene 2 ventas en detalles_venta)
        $canonica = Producto::forceCreate([
            'id_empresa' => 1,
            'codigo' => 'SKU-DUP-01',
            'nombre' => 'Producto Duplicado Test',
            'precio' => 10.00,
            'enable' => true,
            'shopify_variant_id' => 777001,
        ]);

        DB::table('detalles_venta')->insert([
            ['id_venta' => 1, 'id_producto' => $canonica->id, 'cantidad' => 1],
            ['id_venta' => 2, 'id_producto' => $canonica->id, 'cantidad' => 2],
        ]);

        DB::table('inventario')->insert([
            'id_producto' => $canonica->id,
            'id_bodega' => 1,
            'stock' => 10.00,
        ]);

        // 2. Producto duplicado (mismo shopify_variant_id, sin ventas, 5 de stock)
        $duplicado = Producto::forceCreate([
            'id_empresa' => 1,
            'codigo' => 'SKU-DUP-01',
            'nombre' => 'Producto Duplicado Test',
            'precio' => 10.00,
            'enable' => true,
            'shopify_variant_id' => 777001,
        ]);

        DB::table('inventario')->insert([
            'id_producto' => $duplicado->id,
            'id_bodega' => 1,
            'stock' => 5.00,
        ]);

        $service = new ShopifyConsolidationService($this->transformer);
        $stats = $service->deduplicarProductosLocales($this->empresa);

        $this->assertEquals(1, $stats['grupos']);
        $this->assertEquals(1, $stats['duplicados_inactivados']);

        // Verificar que el inventario fue sumado al canónico (10 + 5 = 15)
        $stockCanonica = (float) DB::table('inventario')
            ->where('id_producto', $canonica->id)
            ->where('id_bodega', 1)
            ->value('stock');
        $this->assertEquals(15.00, $stockCanonica);

        // Verificar que el duplicado fue inactivado
        $dupActualizado = Producto::withTrashed()->find($duplicado->id);
        $this->assertEquals('0', (string) $dupActualizado->enable);
        $this->assertNull($dupActualizado->shopify_variant_id);
        $this->assertNotNull($dupActualizado->deleted_at);
    }

    public function test_consolidar_shopify_hacia_smartpyme_revincula_sku_sin_duplicar(): void
    {
        // Producto local con SKU pero con shopify_variant_id viejo/desfasado
        $prodLocal = Producto::forceCreate([
            'id_empresa' => 1,
            'codigo' => 'SKU-REVIN-01',
            'nombre' => 'Zapatos Oxford',
            'precio' => 45.00,
            'enable' => true,
            'shopify_product_id' => 10001,
            'shopify_variant_id' => 20001, // ID viejo en SmartPyme
        ]);

        // Shopify envía el mismo SKU con un variant_id nuevo (ej. variante recreada en Shopify)
        $shopifyProducts = [
            [
                'id' => 10001,
                'title' => 'Zapatos Oxford',
                'status' => 'active',
                'variants' => [
                    [
                        'id' => 20099, // Nuevo variant_id en Shopify
                        'product_id' => 10001,
                        'title' => 'Default Title',
                        'sku' => 'SKU-REVIN-01',
                        'price' => '45.00',
                        'inventory_item_id' => 30099,
                        'inventory_quantity' => 8,
                    ]
                ],
                'options' => [['name' => 'Title']]
            ]
        ];

        $mockClient = $this->createMock(ShopifyApiClient::class);
        $service = $this->getMockBuilder(ShopifyConsolidationService::class)
            ->setConstructorArgs([$this->transformer, $mockClient])
            ->onlyMethods(['obtenerTodosProductosShopify'])
            ->getMock();

        $service->method('obtenerTodosProductosShopify')->willReturn($shopifyProducts);

        $metricas = $service->consolidarShopifyHaciaSmartpyme($this->empresa, $this->user, [
            'deduplicar' => false,
            'vincular_sku' => true,
            'crear_nuevos' => true,
        ]);

        // No debe crear un nuevo producto; debe re-vincular el existente
        $this->assertEquals(0, $metricas['creados']);
        $this->assertEquals(1, $metricas['vinculados']);
        $this->assertEquals(1, Producto::where('id_empresa', 1)->count());

        $prodActualizado = Producto::find($prodLocal->id);
        $this->assertEquals(20099, $prodActualizado->shopify_variant_id);
    }

    public function test_consolidar_shopify_hacia_smartpyme_vincula_por_barcode_cuando_no_hay_sku(): void
    {
        $prodLocal = Producto::forceCreate([
            'id_empresa' => 1,
            'codigo' => null,
            'barcode' => '7412345678901',
            'nombre' => 'Loción Facial',
            'precio' => 12.00,
            'enable' => true,
        ]);

        $shopifyProducts = [
            [
                'id' => 55001,
                'title' => 'Loción Facial',
                'status' => 'active',
                'variants' => [
                    [
                        'id' => 66001,
                        'product_id' => 55001,
                        'title' => 'Default Title',
                        'sku' => '', // Sin SKU
                        'barcode' => '7412345678901', // Coincide con barcode
                        'price' => '12.00',
                        'inventory_item_id' => 77001,
                    ]
                ],
                'options' => [['name' => 'Title']]
            ]
        ];

        $mockClient = $this->createMock(ShopifyApiClient::class);
        $service = $this->getMockBuilder(ShopifyConsolidationService::class)
            ->setConstructorArgs([$this->transformer, $mockClient])
            ->onlyMethods(['obtenerTodosProductosShopify'])
            ->getMock();

        $service->method('obtenerTodosProductosShopify')->willReturn($shopifyProducts);

        $metricas = $service->consolidarShopifyHaciaSmartpyme($this->empresa, $this->user, [
            'deduplicar' => false,
            'vincular_sku' => true,
            'crear_nuevos' => true,
        ]);

        $this->assertEquals(0, $metricas['creados']);
        $this->assertEquals(1, $metricas['vinculados']);
        $this->assertEquals(1, Producto::where('id_empresa', 1)->count());

        $prodActualizado = Producto::find($prodLocal->id);
        $this->assertEquals(66001, $prodActualizado->shopify_variant_id);
    }

    public function test_consolidar_smartpyme_hacia_shopify_reutiliza_producto_padre_existente_por_titulo(): void
    {
        // En SmartPyme existe producto sin vincular
        Producto::forceCreate([
            'id_empresa' => 1,
            'nombre' => 'Sudadera Con Capucha',
            'codigo' => 'SUD-CAP-L',
            'precio' => 25.00,
            'precio_sin_iva' => 25.00,
            'precio_con_iva' => 28.25,
            'enable' => true,
            'shopify_product_id' => null,
            'shopify_variant_id' => null,
        ]);

        // En Shopify ya existe un producto con ese título (ID 999111)
        $catalogoShopify = [
            [
                'id' => 999111,
                'title' => 'Sudadera Con Capucha',
                'variants' => [],
            ]
        ];

        $mockClient = $this->createMock(ShopifyApiClient::class);

        // DEBE llamar a POST products/999111/variants.json anexando la variante al padre existente (no a products.json)
        $mockClient->expects($this->once())
            ->method('post')
            ->with(
                'products/999111/variants.json',
                $this->callback(function ($payload) {
                    return ($payload['variant']['sku'] ?? '') === 'SUD-CAP-L';
                })
            )
            ->willReturn([
                'status' => 'success',
                'body' => ['variant' => ['id' => 888222, 'inventory_item_id' => 777333]]
            ]);

        $service = $this->getMockBuilder(ShopifyConsolidationService::class)
            ->setConstructorArgs([$this->transformer, $mockClient])
            ->onlyMethods(['obtenerTodosProductosShopify'])
            ->getMock();

        $service->method('obtenerTodosProductosShopify')->willReturn($catalogoShopify);

        $metricas = $service->consolidarSmartpymeHaciaShopify($this->empresa, $this->user, [
            'deduplicar' => false,
            'vincular_sku' => true,
            'crear_nuevos' => true,
        ]);

        $this->assertEquals(1, $metricas['creados']);
        $this->assertEquals(0, $metricas['errores']);

        $prodActualizado = Producto::where('codigo', 'SUD-CAP-L')->first();
        $this->assertEquals(999111, $prodActualizado->shopify_product_id);
        $this->assertEquals(888222, $prodActualizado->shopify_variant_id);
    }
}

