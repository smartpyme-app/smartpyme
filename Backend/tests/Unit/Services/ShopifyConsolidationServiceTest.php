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

        Schema::create('productos', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
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
}

