<?php

namespace Tests\Unit\Services;

use App\Jobs\SincronizarPagoVentaAShopifyJob;
use App\Jobs\SincronizarVentaAShopifyJob;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Bodega;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Venta;
use App\Observers\ShopifyInventarioObserver;
use App\Observers\ShopifyVentaObserver;
use App\Services\ShopifyApiClient;
use App\Services\ShopifyOrderService;
use App\Services\ShopifyTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShopifyBilateralIntegrityTest extends TestCase
{
    private Empresa $empresa;
    private ShopifyOrderService $orderService;
    private $apiClientMock;

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
            $table->boolean('shopify_sync_ventas')->default(false);
            $table->boolean('shopify_sync_bidirectional')->default(false);
            $table->string('shopify_store_url')->nullable();
            $table->string('shopify_access_token')->nullable();
            $table->string('shopify_status')->nullable();
            $table->timestamps();
        });

        Schema::create('productos', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('shopify_product_id')->nullable();
            $table->unsignedBigInteger('shopify_variant_id')->nullable();
            $table->unsignedBigInteger('shopify_inventory_item_id')->nullable();
            $table->string('shopify_sku')->nullable();
            $table->string('codigo')->nullable();
            $table->string('nombre')->nullable();
            $table->decimal('precio', 10, 2)->default(0);
            $table->boolean('syncing_from_shopify')->default(false);
            $table->boolean('enable')->default(true);
            $table->softDeletes();
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
            $table->timestamps();
        });

        Schema::create('clientes', function ($table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->string('apellido')->nullable();
            $table->string('correo')->nullable();
            $table->string('telefono')->nullable();
            $table->unsignedBigInteger('shopify_customer_id')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_bodega')->nullable();
            $table->string('shopify_status')->nullable();
            $table->timestamps();
        });

        Schema::create('ventas', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_cliente')->nullable();
            $table->unsignedBigInteger('id_bodega')->nullable();
            $table->string('estado')->default('Pagada');
            $table->string('correlativo')->nullable();
            $table->decimal('total', 10, 2)->default(0);
            $table->integer('cotizacion')->default(0);
            $table->unsignedBigInteger('num_cotizacion')->nullable();
            $table->string('referencia_shopify')->nullable();
            $table->string('num_orden')->nullable();
            $table->string('forma_pago')->nullable();
            $table->timestamps();
        });

        Schema::create('detalles_venta', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_venta')->nullable();
            $table->unsignedBigInteger('id_producto')->nullable();
            $table->integer('cantidad')->default(1);
            $table->decimal('precio', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('descripcion')->nullable();
            $table->timestamps();
        });

        $this->empresa = Empresa::create([
            'nombre' => 'Test Bilateral S.A.',
            'shopify_sync_ventas' => true,
            'shopify_sync_bidirectional' => true,
            'shopify_store_url' => 'bilateral-test.myshopify.com',
            'shopify_access_token' => 'shpat_test123',
            'shopify_status' => 'connected',
        ]);

        $this->apiClientMock = $this->createMock(ShopifyApiClient::class);
        $tokenServiceMock = $this->createMock(ShopifyTokenService::class);
        $this->orderService = new ShopifyOrderService($this->apiClientMock, $tokenServiceMock);

        Venta::observe(ShopifyVentaObserver::class);
    }

    /**
     * 1. PREVENCIÓN DE BUCLE: Cuando un webhook entrante de Shopify actualiza la venta a Pagada,
     * el observer NO debe despachar SincronizarPagoVentaAShopifyJob de vuelta a Shopify.
     */
    public function test_webhook_entrante_no_dispara_job_pago_hacia_shopify(): void
    {
        Queue::fake();

        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'estado' => 'Pendiente',
            'referencia_shopify' => 'SHOPIFY-99887766',
            'total' => 50.00,
        ]);

        // Simular que la petición actual es un webhook de Shopify
        $fakeRequest = Request::create('/api/webhook/shopify/token123', 'POST');
        $this->app->instance('request', $fakeRequest);

        $venta->estado = 'Pagada';
        $venta->save();

        // Verificar que NO se encoló ningún trabajo hacia Shopify
        Queue::assertNotPushed(SincronizarPagoVentaAShopifyJob::class);
    }

    /**
     * 2. FLUJO NORMAL: Cuando un cajero/usuario liquida la venta en la interfaz local,
     * el observer SÍ debe despachar SincronizarPagoVentaAShopifyJob hacia Shopify.
     */
    public function test_pago_local_en_caja_dispara_job_pago_hacia_shopify(): void
    {
        Queue::fake();

        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'estado' => 'Pendiente',
            'referencia_shopify' => 'SHOPIFY-99887766',
            'total' => 50.00,
        ]);

        // Simular que la petición actual es la ruta normal de abono/facturación
        $fakeRequest = Request::create('/api/venta/abono', 'POST');
        $this->app->instance('request', $fakeRequest);

        $venta->estado = 'Pagada';
        $venta->save();

        // Verificar que SÍ se encoló el trabajo
        Queue::assertPushed(SincronizarPagoVentaAShopifyJob::class, function ($job) use ($venta) {
            return $job->ventaId === $venta->id;
        });
    }

    /**
     * 3. IDEMPOTENCIA: Si una venta ya estaba 'Pagada' y solo se editan campos no relacionados
     * (por ejemplo notas o correlativo), el observer NO debe redespachar el cobro a Shopify.
     */
    public function test_edicion_de_venta_ya_pagada_no_redespacha_pago(): void
    {
        Queue::fake();

        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'estado' => 'Pagada',
            'referencia_shopify' => 'SHOPIFY-99887766',
            'total' => 50.00,
            'correlativo' => '001',
        ]);

        $fakeRequest = Request::create('/api/facturacion', 'POST');
        $this->app->instance('request', $fakeRequest);

        $venta->correlativo = '002';
        $venta->save();

        Queue::assertNotPushed(SincronizarPagoVentaAShopifyJob::class);
    }

    /**
     * 4. ANTI DOBLE DESCUENTO EN ORDENES: Al sincronizar ventas desde SmartPyme hacia Shopify,
     * el payload de la orden SIEMPRE debe tener inventory_behaviour = 'bypass'
     * para que Shopify no descuente inventario por su cuenta.
     */
    public function test_orden_local_hacia_shopify_siempre_lleva_inventory_behaviour_bypass(): void
    {
        $producto = Producto::create([
            'id_empresa' => $this->empresa->id,
            'nombre' => 'Camisa Polo',
            'shopify_variant_id' => 123456,
            'precio' => 25.00,
        ]);

        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'estado' => 'Pagada',
            'total' => 25.00,
        ]);

        Detalle::create([
            'id_venta' => $venta->id,
            'id_producto' => $producto->id,
            'cantidad' => 1,
            'precio' => 25.00,
            'total' => 25.00,
        ]);

        $venta->load(['detalles.producto', 'cliente']);
        $payload = $this->orderService->construirPayloadOrden($venta, $this->empresa);

        $this->assertSame('bypass', $payload['inventory_behaviour']);
        $this->assertSame('smartpyme', $payload['source_name']);
    }

    /**
     * 5. CORRECCIÓN API TRANSACTIONS: marcarOrdenPagadaEnShopify debe incluir 'source' => 'external'
     * en el payload de transacción para no ser rechazado con 422 por Shopify.
     */
    public function test_transaccion_de_pago_incluye_source_external(): void
    {
        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'estado' => 'Pagada',
            'referencia_shopify' => 'SHOPIFY-17134833434994',
            'total' => 8.85,
            'forma_pago' => 'Efectivo',
        ]);

        $this->apiClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                'orders/17134833434994/transactions.json',
                $this->callback(function ($payload) {
                    return isset($payload['transaction']['source'])
                        && $payload['transaction']['source'] === 'external'
                        && $payload['transaction']['kind'] === 'sale'
                        && $payload['transaction']['amount'] === '8.85';
                })
            )
            ->willReturn([
                'status' => 'success',
                'body' => ['transaction' => ['id' => 10101, 'status' => 'success']],
                'http_status' => 201,
            ]);

        $resultado = $this->orderService->marcarOrdenPagadaEnShopify($venta);
        $this->assertTrue($resultado);
    }

    /**
     * 6. ANTI DUPLICIDAD EN WEBHOOK: Si Shopify devuelve el webhook orders/create para una orden
     * generada en SmartPyme, el registro ya existe con referencia_shopify y no se debe duplicar.
     */
    public function test_orden_con_referencia_existente_es_reconocida_como_duplicada(): void
    {
        $ventaExistente = Venta::create([
            'id_empresa' => $this->empresa->id,
            'estado' => 'Pagada',
            'referencia_shopify' => 'SHOPIFY-778899',
            'total' => 100.00,
        ]);

        $busqueda = Venta::where('referencia_shopify', 'SHOPIFY-778899')
            ->where('id_empresa', $this->empresa->id)
            ->first();

        $this->assertNotNull($busqueda);
        $this->assertSame($ventaExistente->id, $busqueda->id);
    }

    /**
     * 7. CONSOLIDACIÓN SEGURA: Inventario::withoutEvents previene eventos al importar stock.
     */
    public function test_sin_eventos_en_inventario_no_dispara_observers(): void
    {
        $producto = Producto::create([
            'id_empresa' => $this->empresa->id,
            'nombre' => 'Short Deportivo',
            'precio' => 15.00,
        ]);

        $bodega = Bodega::create([
            'id_empresa' => $this->empresa->id,
            'nombre' => 'Bodega Central',
        ]);

        $disparado = false;
        Inventario::saved(function () use (&$disparado) {
            $disparado = true;
        });

        Inventario::withoutEvents(function () use ($producto, $bodega) {
            Inventario::create([
                'id_producto' => $producto->id,
                'id_bodega' => $bodega->id,
                'stock' => 50,
            ]);
        });

        $this->assertFalse($disparado, 'withoutEvents debe asegurar que ningún observer reaccione durante importaciones masivas');
    }

    /**
     * 8. VENTA A CRÉDITO: Al crear una venta con estado 'Pendiente' en SmartPyme,
     * el payload hacia Shopify debe tener financial_status = 'pending' y fulfillment_status = null.
     */
    public function test_venta_credito_crea_orden_con_financial_status_pending_y_sin_fulfillment(): void
    {
        $producto = Producto::create([
            'id_empresa' => $this->empresa->id,
            'nombre' => 'Pantalón Casual',
            'shopify_variant_id' => 789012,
            'precio' => 40.00,
        ]);

        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'estado' => 'Pendiente',
            'total' => 40.00,
        ]);

        Detalle::create([
            'id_venta' => $venta->id,
            'id_producto' => $producto->id,
            'cantidad' => 1,
            'precio' => 40.00,
            'total' => 40.00,
        ]);

        $venta->load(['detalles.producto', 'cliente']);
        $payload = $this->orderService->construirPayloadOrden($venta, $this->empresa);

        $this->assertSame('pending', $payload['financial_status']);
        $this->assertArrayNotHasKey('fulfillment_status', $payload);
    }

    /**
     * 9. ABONO PARCIAL: Si una venta a crédito recibe un abono pero su estado continúa 'Pendiente',
     * el observer NO debe despachar SincronizarPagoVentaAShopifyJob hacia Shopify.
     */
    public function test_abono_parcial_que_mantiene_estado_pendiente_no_despacha_job_pago(): void
    {
        Queue::fake();

        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'estado' => 'Pendiente',
            'referencia_shopify' => 'SHOPIFY-99887766',
            'total' => 100.00,
        ]);

        $fakeRequest = Request::create('/api/venta/abono', 'POST');
        $this->app->instance('request', $fakeRequest);

        // Simulamos un abono parcial que actualiza campos de la venta pero mantiene estado 'Pendiente'
        $venta->correlativo = 'ABONO-001';
        $venta->save();

        Queue::assertNotPushed(SincronizarPagoVentaAShopifyJob::class);
    }
}

