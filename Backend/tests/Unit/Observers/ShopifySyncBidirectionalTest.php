<?php

namespace Tests\Unit\Observers;

use App\Http\Controllers\Api\Webhook\ShopifyController;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Producto;
use App\Models\User;
use App\Observers\ShopifyProductoObserver;
use App\Services\ImpuestosService;
use App\Services\ShippingService;
use App\Services\Shopify\ShopifyClienteService;
use App\Services\Shopify\ShopifyVentaService;
use App\Services\ShopifyImageService;
use App\Services\ShopifyStockService;
use App\Services\ShopifySyncCache;
use App\Services\ShopifyTransformer;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class ShopifySyncBidirectionalTest extends TestCase
{
    private $stockServiceMock;
    private $cacheMock;
    private ShopifyProductoObserver $observer;
    private ShopifyTransformer $transformer;
    private ShopifyController $controller;

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

        Schema::create('empresa_configuracion', function ($table) {
            $table->id();
            $table->unsignedInteger('empresa_id');
            $table->string('pais', 3)->default('SV');
            $table->string('modulo', 50);
            $table->json('configuracion');
            $table->timestamps();
        });

        Schema::create('users', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_bodega')->nullable();
            $table->string('shopify_status')->nullable();
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

        Schema::create('sucursal_bodegas', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('nombre')->nullable();
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
            $table->boolean('enable')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        $this->stockServiceMock = $this->createMock(ShopifyStockService::class);
        $this->cacheMock = $this->createMock(ShopifySyncCache::class);

        $this->observer = new ShopifyProductoObserver(
            $this->stockServiceMock,
            $this->cacheMock
        );

        $impuestosService = $this->createMock(ImpuestosService::class);
        $this->transformer = new ShopifyTransformer($impuestosService);

        $shippingService = $this->createMock(ShippingService::class);
        $imageService = $this->createMock(ShopifyImageService::class);

        $this->controller = new ShopifyController(
            $this->transformer,
            $this->cacheMock,
            $shippingService,
            $impuestosService,
            $this->createMock(ShopifyVentaService::class),
            $this->createMock(ShopifyClienteService::class),
            $imageService
        );
    }

    /**
     * Verifica que si shopify_sync_bidirectional está apagado,
     * crear un producto no invoque la sincronización hacia Shopify.
     */
    public function test_observer_no_sincroniza_creacion_cuando_bidireccional_esta_desactivado(): void
    {
        $empresa = Empresa::create([
            'shopify_sync_bidirectional' => false,
        ]);

        $this->stockServiceMock
            ->expects($this->never())
            ->method('createdProductoCompletoEnShopify');

        $producto = new Producto();
        $producto->id = 1001;
        $producto->nombre = 'Producto Local';
        $producto->id_empresa = $empresa->id;

        $this->observer->created($producto);
    }

    /**
     * Verifica que si shopify_sync_bidirectional está apagado,
     * actualizar un producto no invoque la sincronización hacia Shopify.
     */
    public function test_observer_no_sincroniza_actualizacion_cuando_bidireccional_esta_desactivado(): void
    {
        $empresa = Empresa::create([
            'shopify_sync_bidirectional' => false,
        ]);

        $this->stockServiceMock
            ->expects($this->never())
            ->method('actualizarProductoCompletoEnShopify');

        $producto = new Producto();
        $producto->id = 1002;
        $producto->nombre = 'Producto Modificado';
        $producto->id_empresa = $empresa->id;

        $this->observer->updated($producto);
    }

    /**
     * Verifica que si shopify_sync_bidirectional está activado y la empresa está conectada,
     * al crear un producto local se dispare la sincronización hacia Shopify.
     */
    public function test_observer_sincroniza_creacion_cuando_bidireccional_esta_activado(): void
    {
        $empresa = Empresa::create([
            'shopify_sync_bidirectional' => true,
            'shopify_store_url' => 'https://mitienda.myshopify.com',
            'shopify_access_token' => 'shpat_test123456789',
            'shopify_status' => 'connected',
        ]);

        $user = User::create([
            'id_empresa' => $empresa->id,
            'shopify_status' => 'connected',
        ]);

        $producto = new Producto();
        $producto->id = 1006;
        $producto->nombre = 'Nuevo Producto Local';
        $producto->id_empresa = $empresa->id;

        $this->stockServiceMock
            ->expects($this->once())
            ->method('createdProductoCompletoEnShopify')
            ->with($producto->id, $user->id, true)
            ->willReturn(true);

        $this->observer->created($producto);
    }

    /**
     * Un servicio de la categoría envios no se publica en Shopify.
     * Si se publica, el products/create de vuelta lo convierte en producto y la venta pierde la línea.
     */
    public function test_observer_no_publica_un_servicio_de_envio(): void
    {
        $empresa = Empresa::create([
            'shopify_sync_bidirectional' => true,
            'shopify_store_url' => 'https://mitienda.myshopify.com',
            'shopify_access_token' => 'shpat_test123456789',
            'shopify_status' => 'connected',
        ]);

        $categoria = new Categoria();
        $categoria->nombre = 'envios';

        $producto = new Producto();
        $producto->id = 1007;
        $producto->nombre = 'cargo express';
        $producto->tipo = 'Servicio';
        $producto->id_empresa = $empresa->id;
        $producto->setRelation('categoria', $categoria);

        $this->stockServiceMock
            ->expects($this->never())
            ->method('createdProductoCompletoEnShopify');

        $this->observer->created($producto);
    }

    /**
     * Verifica que un producto con shopify_product_id o syncing_from_shopify
     * no intente crearse nuevamente en Shopify (anti-duplicación y prevención de ciclo).
     */
    public function test_created_sync_bidirectional_evita_ciclos_si_producto_proviene_de_shopify(): void
    {
        $this->stockServiceMock
            ->expects($this->never())
            ->method('createdProductoCompletoEnShopify');

        $producto = new Producto();
        $producto->id = 1003;
        $producto->shopify_product_id = 88776655;
        $producto->id_empresa = 1;

        $this->observer->createdSyncBidirectional($producto);

        $producto2 = new Producto();
        $producto2->id = 1004;
        $producto2->syncing_from_shopify = true;
        $producto2->id_empresa = 1;

        $this->observer->createdSyncBidirectional($producto2);
    }

    /**
     * Verifica que si el producto está bloqueado en caché por una sincronización previa (lockSync),
     * el observador no envíe actualizaciones redundantes hacia Shopify (Anti Ping-Pong).
     */
    public function test_updated_sync_bidirectional_respeta_candado_cache_para_evitar_ping_pong(): void
    {
        $this->cacheMock
            ->method('isLocked')
            ->with(1005)
            ->willReturn(true);

        $this->stockServiceMock
            ->expects($this->never())
            ->method('actualizarProductoCompletoEnShopify');

        $producto = new Producto();
        $producto->id = 1005;
        $producto->enable = 1;
        $producto->id_empresa = 1;

        $this->observer->updatedSyncBidirectional($producto);
    }

    /**
     * Verifica que el transformer descomponga un producto con múltiples variantes
     * en registros separados con sus IDs y opciones exactas.
     */
    public function test_transformar_producto_desglosa_variantes_correctamente(): void
    {
        $shopifyData = [
            'id' => 777111,
            'title' => 'Camisa Formal',
            'status' => 'active',
            'options' => [
                ['name' => 'Color'],
                ['name' => 'Talla'],
            ],
            'variants' => [
                [
                    'id' => 888001,
                    'title' => 'Blanco / S',
                    'price' => '25.00',
                    'sku' => 'CAM-BLA-S',
                    'barcode' => '7410001',
                    'inventory_item_id' => 999001,
                    'inventory_quantity' => 10,
                    'option1' => 'Blanco',
                    'option2' => 'S',
                ],
                [
                    'id' => 888002,
                    'title' => 'Azul / M',
                    'price' => '27.50',
                    'sku' => 'CAM-AZU-M',
                    'barcode' => '7410002',
                    'inventory_item_id' => 999002,
                    'inventory_quantity' => 5,
                    'option1' => 'Azul',
                    'option2' => 'M',
                ],
            ],
        ];

        $filas = $this->transformer->transformarProductoDesdeShopify($shopifyData, 1, 1, 1);

        $this->assertCount(2, $filas);

        // Variante 1
        $this->assertSame(777111, $filas[0]['shopify_product_id']);
        $this->assertSame(888001, $filas[0]['shopify_variant_id']);
        $this->assertSame('CAM-BLA-S', $filas[0]['codigo']);
        $this->assertSame('Blanco - S', $filas[0]['nombre_variante']);
        $this->assertSame('Blanco', $filas[0]['option1_value']);
        $this->assertSame('S', $filas[0]['option2_value']);

        // Variante 2
        $this->assertSame(777111, $filas[1]['shopify_product_id']);
        $this->assertSame(888002, $filas[1]['shopify_variant_id']);
        $this->assertSame('CAM-AZU-M', $filas[1]['codigo']);
        $this->assertSame('Azul - M', $filas[1]['nombre_variante']);
        $this->assertSame('Azul', $filas[1]['option1_value']);
        $this->assertSame('M', $filas[1]['option2_value']);
    }

    /**
     * Verifica que un producto simple con variante Default Title no tenga nombre_variante.
     */
    public function test_transformar_producto_default_title_no_asigna_nombre_variante(): void
    {
        $shopifyData = [
            'id' => 777222,
            'title' => 'Termo Metálico',
            'status' => 'active',
            'options' => [['name' => 'Title']],
            'variants' => [
                [
                    'id' => 888003,
                    'title' => 'Default Title',
                    'price' => '12.00',
                    'sku' => 'TERMO-01',
                    'inventory_item_id' => 999003,
                    'inventory_quantity' => 15,
                ],
            ],
        ];

        $filas = $this->transformer->transformarProductoDesdeShopify($shopifyData, 1, 1, 1);

        $this->assertCount(1, $filas);
        $this->assertNull($filas[0]['nombre_variante']);
        $this->assertSame('Termo Metálico', $filas[0]['nombre']);
    }

    /**
     * Verifica que buscarProductoExistente diferencie dos variantes del mismo producto de Shopify
     * exclusivamente por su shopify_variant_id, impidiendo colisiones o sobreescrituras accidentales.
     */
    public function test_buscar_producto_existente_resuelve_variantes_por_variant_id_sin_colision(): void
    {
        $empresa = Empresa::create(['shopify_sync_bidirectional' => true]);

        // Crear dos variantes registradas en SmartPyme bajo el mismo shopify_product_id y mismo nombre
        $varianteBlanca = Producto::create([
            'id_empresa' => $empresa->id,
            'nombre' => 'Camisa Formal',
            'nombre_variante' => 'Blanco - S',
            'codigo' => 'CAM-BLA-S',
            'shopify_sku' => 'CAM-BLA-S',
            'shopify_product_id' => 5001,
            'shopify_variant_id' => 9001,
            'precio' => 25.00,
        ]);

        $varianteAzul = Producto::create([
            'id_empresa' => $empresa->id,
            'nombre' => 'Camisa Formal',
            'nombre_variante' => 'Azul - M',
            'codigo' => 'CAM-AZU-M',
            'shopify_sku' => 'CAM-AZU-M',
            'shopify_product_id' => 5001,
            'shopify_variant_id' => 9002,
            'precio' => 28.00,
        ]);

        $reflector = new ReflectionClass(ShopifyController::class);
        $method = $reflector->getMethod('buscarProductoExistente');
        $method->setAccessible(true);

        // Simulamos búsqueda entrante para la variante Azul (9002)
        $productoDataAzul = [
            'shopify_variant_id' => 9002,
            'shopify_sku' => 'CAM-AZU-M',
            'codigo' => 'CAM-AZU-M',
        ];

        $encontrado = $method->invoke($this->controller, 5001, $productoDataAzul, $empresa->id);

        $this->assertNotNull($encontrado);
        $this->assertSame($varianteAzul->id, $encontrado->id);
        $this->assertSame('Azul - M', $encontrado->nombre_variante);
        $this->assertNotSame($varianteBlanca->id, $encontrado->id);
    }

    /**
     * Verifica que si un producto ya posee shopify_product_id o shopify_variant_id,
     * ante un fallo en actualizarProductoPorId NUNCA llame a crearNuevoProducto ni
     * sobreescriba los identificadores de Shopify.
     */
    public function test_actualizar_producto_completo_no_crea_producto_si_ya_tiene_shopify_id(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            '*' => \Illuminate\Support\Facades\Http::response(['error' => 'Simulated error'], 500),
        ]);

        $empresa = Empresa::create([
            'shopify_sync_bidirectional' => true,
            'shopify_store_url' => 'https://mitienda.myshopify.com',
            'shopify_access_token' => 'shpat_test123',
            'shopify_consumer_secret' => 'secret123',
            'shopify_status' => 'connected',
        ]);

        $user = User::create([
            'id_empresa' => $empresa->id,
            'id_bodega' => 1,
            'shopify_status' => 'connected',
        ]);

        $producto = Producto::create([
            'id_empresa' => $empresa->id,
            'nombre' => 'Jumpsuit Test',
            'codigo' => 'JUMP-01',
            'shopify_product_id' => 8937577512983,
            'shopify_variant_id' => 47663254142999,
            'precio' => 50.00,
        ]);

        $service = new ShopifyStockService();

        $resultado = $service->actualizarProductoCompletoEnShopify($producto->id, $user->id);

        $this->assertFalse($resultado);

        // NUNCA debe intentar crear un nuevo producto en Shopify (POST products.json) si ya tiene shopify_variant_id / shopify_product_id
        \Illuminate\Support\Facades\Http::assertNotSent(function ($request) {
            return $request->method() === 'POST' && str_contains($request->url(), 'products.json');
        });

        // Asegurar que los IDs originales no fueron sobreescritos
        $producto->refresh();
        $this->assertEquals(8937577512983, $producto->shopify_product_id);
        $this->assertEquals(47663254142999, $producto->shopify_variant_id);
    }

    /**
     * Verifica que handle() deduplique webhooks entrantes que compartan el mismo X-Shopify-Webhook-Id.
     */
    public function test_handle_deduplica_webhooks_con_mismo_webhook_id(): void
    {
        $empresa = Empresa::create([
            'woocommerce_api_key' => 'token123456789',
            'shopify_status' => 'connected',
        ]);

        User::create([
            'id_empresa' => $empresa->id,
            'id_bodega' => 1,
            'shopify_status' => 'connected',
        ]);

        $request = \Illuminate\Http\Request::create('/webhook', 'POST', [
            'id' => 888111,
            'title' => 'Producto Prueba',
            'variants' => [],
        ]);
        $request->headers->set('X-Shopify-Topic', 'products/update');
        $request->headers->set('X-Shopify-Webhook-Id', 'webhook-uuid-test-12345');

        // Primer envío: procesa
        $resp1 = $this->controller->handle('token123456789', $request);
        $this->assertEquals(200, $resp1->getStatusCode());

        // Segundo envío con mismo X-Shopify-Webhook-Id: deduplicado inmediatamente
        $resp2 = $this->controller->handle('token123456789', $request);
        $this->assertEquals(200, $resp2->getStatusCode());
        $data = json_decode($resp2->getContent(), true);
        $this->assertSame('Webhook ya procesado previamente', $data['message']);
    }

    /**
     * Verifica que actualizarInventario con origen shopify use withoutEvents para no disparar observers.
     */
    public function test_actualizar_inventario_silencia_eventos_y_no_dispara_observador(): void
    {
        $empresa = Empresa::create([
            'shopify_sync_bidirectional' => true,
        ]);

        $producto = Producto::create([
            'id_empresa' => $empresa->id,
            'nombre' => 'Producto Sin Eventos',
            'shopify_product_id' => 12345,
            'shopify_variant_id' => 67890,
            'precio' => 10.00,
        ]);

        // Aseguramos que el mock del stock service NUNCA sea llamado
        $this->stockServiceMock->expects($this->never())->method('actualizarProductoCompletoEnShopify');

        $reflector = new ReflectionClass(ShopifyController::class);
        $method = $reflector->getMethod('actualizarInventario');
        $method->setAccessible(true);

        $method->invoke(
            $this->controller,
            $producto->id,
            5,
            1,
            1,
            ['origen' => 'shopify', 'tipo' => 'inventario_inicial']
        );

        $producto->refresh();
        $this->assertFalse((bool)$producto->syncing_from_shopify);
    }
}
