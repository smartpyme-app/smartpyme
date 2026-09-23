<?php

namespace Tests\Unit\Services;

use App\Jobs\SincronizarPagoVentaAShopifyJob;
use App\Jobs\SincronizarVentaAShopifyJob;
use App\Models\Admin\Empresa;
use App\Models\Admin\ShopifyLocation;
use App\Models\Inventario\Producto;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Detalle;
use App\Models\Ventas\Venta;
use App\Services\ShopifyApiClient;
use App\Services\ShopifyOrderService;
use App\Services\ShopifyTokenService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShopifyOrderServiceTest extends TestCase
{
    private $apiClientMock;
    private $tokenServiceMock;
    private ShopifyOrderService $orderService;
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
            $table->string('nombre')->nullable();
            $table->boolean('shopify_sync_ventas')->default(false);
            $table->boolean('shopify_sync_bidirectional')->default(false);
            $table->string('shopify_store_url')->nullable();
            $table->string('shopify_access_token')->nullable();
            $table->string('shopify_status')->nullable();
            $table->timestamps();
        });

        Schema::create('shopify_locations', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('shopify_location_id')->nullable();
            $table->unsignedBigInteger('id_bodega')->nullable();
            $table->boolean('sincronizar_stock')->default(true);
            $table->timestamps();
        });

        Schema::create('clientes', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('nombre')->nullable();
            $table->string('apellido')->nullable();
            $table->string('correo')->nullable();
            $table->string('telefono')->nullable();
            $table->unsignedBigInteger('shopify_customer_id')->nullable();
            $table->timestamps();
        });

        Schema::create('productos', function ($table) {
            $table->id();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('shopify_product_id')->nullable();
            $table->unsignedBigInteger('shopify_variant_id')->nullable();
            $table->string('shopify_sku')->nullable();
            $table->string('codigo')->nullable();
            $table->string('nombre')->nullable();
            $table->string('nombre_variante')->nullable();
            $table->decimal('precio', 10, 2)->default(0);
            $table->boolean('enable')->default(true);
            $table->softDeletes();
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
            $table->decimal('cantidad', 10, 2)->default(1);
            $table->decimal('precio', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('descripcion')->nullable();
            $table->timestamps();
        });

        $this->apiClientMock = $this->createMock(ShopifyApiClient::class);
        $this->tokenServiceMock = $this->createMock(ShopifyTokenService::class);

        $this->orderService = new ShopifyOrderService(
            $this->apiClientMock,
            $this->tokenServiceMock
        );

        $this->empresa = Empresa::create([
            'nombre' => 'Mi Empresa Test',
            'shopify_sync_ventas' => true,
            'shopify_store_url' => 'https://mitienda.myshopify.com',
            'shopify_access_token' => 'shpat_test123456',
            'shopify_status' => 'connected',
        ]);
    }

    /**
     * Verifica que si el switch shopify_sync_ventas está apagado, no se sincroniza la venta.
     */
    public function test_no_sincroniza_venta_si_switch_esta_apagado(): void
    {
        $this->empresa->update(['shopify_sync_ventas' => false]);

        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'estado' => 'Pagada',
            'total' => 50.00,
        ]);

        $this->apiClientMock
            ->expects($this->never())
            ->method('post');

        $resultado = $this->orderService->crearOrdenDesdeVenta($venta);

        $this->assertFalse($resultado);
    }

    /**
     * Verifica que si la venta es una cotización, no se sincroniza hacia Shopify.
     */
    public function test_no_sincroniza_venta_si_es_cotizacion(): void
    {
        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'estado' => 'Pendiente',
            'cotizacion' => 1,
            'total' => 100.00,
        ]);

        $this->apiClientMock
            ->expects($this->never())
            ->method('post');

        $resultado = $this->orderService->crearOrdenDesdeVenta($venta);

        $this->assertFalse($resultado);
    }

    /**
     * Verifica que si la venta ya provino de Shopify (tiene referencia_shopify), no se reenvíe.
     */
    public function test_no_sincroniza_venta_si_ya_proviene_de_shopify_referencia_existente(): void
    {
        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'estado' => 'Pagada',
            'referencia_shopify' => 'SHOPIFY-77665544',
            'total' => 80.00,
        ]);

        $this->apiClientMock
            ->expects($this->never())
            ->method('post');

        $resultado = $this->orderService->crearOrdenDesdeVenta($venta);

        $this->assertFalse($resultado);
    }

    /**
     * Verifica la construcción correcta del payload:
     * - variant_id para productos vinculados
     * - inventory_behaviour: bypass (anti doble descuento)
     * - fulfillment_status: fulfilled (para ventas pagadas)
     * - source_name: smartpyme
     */
    public function test_construye_payload_con_variant_id_y_bypass_de_inventario(): void
    {
        $producto = Producto::create([
            'id_empresa' => $this->empresa->id,
            'nombre' => 'Zapato Deportivo',
            'shopify_variant_id' => 88899911,
            'shopify_sku' => 'ZAP-DEP-42',
            'precio' => 60.00,
        ]);

        $cliente = Cliente::create([
            'id_empresa' => $this->empresa->id,
            'nombre' => 'Carlos',
            'apellido' => 'Gomez',
            'correo' => 'carlos@example.com',
            'shopify_customer_id' => 99911122,
        ]);

        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'id_cliente' => $cliente->id,
            'estado' => 'Pagada',
            'correlativo' => 'FAC-00123',
            'total' => 120.00,
        ]);

        Detalle::create([
            'id_venta' => $venta->id,
            'id_producto' => $producto->id,
            'cantidad' => 2,
            'precio' => 60.00,
            'total' => 120.00,
        ]);

        $venta->load(['detalles.producto', 'cliente']);

        $payload = $this->orderService->construirPayloadOrden($venta, $this->empresa);

        $this->assertNotNull($payload);
        $this->assertSame('paid', $payload['financial_status']);
        $this->assertSame('fulfilled', $payload['fulfillment_status']);
        $this->assertSame('bypass', $payload['inventory_behaviour']);
        $this->assertSame('smartpyme', $payload['source_name']);
        $this->assertCount(1, $payload['line_items']);
        $this->assertSame(88899911, $payload['line_items'][0]['variant_id']);
        $this->assertSame(2, $payload['line_items'][0]['quantity']);
        $this->assertSame('60.00', $payload['line_items'][0]['price']);
        $this->assertSame('ZAP-DEP-42', $payload['line_items'][0]['sku']);
        $this->assertSame(99911122, $payload['customer']['id']);
    }

    /**
     * Verifica que tras una respuesta exitosa de Shopify, la venta en SmartPyme
     * se actualice con la referencia_shopify y num_orden correspondientes.
     */
    public function test_actualiza_referencia_shopify_en_venta_tras_creacion_exitosa(): void
    {
        $producto = Producto::create([
            'id_empresa' => $this->empresa->id,
            'nombre' => 'Mochila Urbana',
            'shopify_variant_id' => 44332211,
            'precio' => 35.00,
        ]);

        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'estado' => 'Pagada',
            'correlativo' => '0000456',
            'total' => 35.00,
        ]);

        Detalle::create([
            'id_venta' => $venta->id,
            'id_producto' => $producto->id,
            'cantidad' => 1,
            'precio' => 35.00,
            'total' => 35.00,
        ]);

        $mockResponse = $this->createMock(Response::class);
        $mockResponse->method('successful')->willReturn(true);
        $mockResponse->method('json')->willReturn([
            'order' => [
                'id' => 7788990011,
                'order_number' => 1085,
                'name' => '#1085',
            ],
        ]);

        $this->apiClientMock
            ->expects($this->once())
            ->method('post')
            ->with('orders.json', $this->isType('array'))
            ->willReturn($mockResponse);

        $resultado = $this->orderService->crearOrdenDesdeVenta($venta);

        $this->assertIsArray($resultado);
        $this->assertSame(7788990011, $resultado['id']);

        $ventaFresca = Venta::find($venta->id);
        $this->assertSame('SHOPIFY-7788990011', $ventaFresca->referencia_shopify);
        $this->assertSame('1085', $ventaFresca->num_orden);
    }

    /**
     * Verifica que cuando ShopifyApiClient retorna un array asociativo (comportamiento real),
     * la orden se procese y registre correctamente sin errores de llamada a método.
     */
    public function test_actualiza_referencia_shopify_con_array_response_de_api_client(): void
    {
        $producto = Producto::create([
            'id_empresa' => $this->empresa->id,
            'nombre' => 'Gorra Trucker',
            'shopify_variant_id' => 55667788,
            'precio' => 15.00,
        ]);

        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'estado' => 'Pagada',
            'correlativo' => '0000789',
            'total' => 15.00,
        ]);

        Detalle::create([
            'id_venta' => $venta->id,
            'id_producto' => $producto->id,
            'cantidad' => 1,
            'precio' => 15.00,
            'total' => 15.00,
        ]);

        $arrayResponse = [
            'status' => 'success',
            'body' => [
                'order' => [
                    'id' => 9911223344,
                    'order_number' => 1099,
                    'name' => '#1099',
                ],
            ],
            'http_status' => 201,
        ];

        $this->apiClientMock
            ->expects($this->once())
            ->method('post')
            ->with('orders.json', $this->isType('array'))
            ->willReturn($arrayResponse);

        $resultado = $this->orderService->crearOrdenDesdeVenta($venta);

        $this->assertIsArray($resultado);
        $this->assertSame(9911223344, $resultado['id']);

        $ventaFresca = Venta::find($venta->id);
        $this->assertSame('SHOPIFY-9911223344', $ventaFresca->referencia_shopify);
        $this->assertSame('1099', $ventaFresca->num_orden);
    }

    /**
     * Verifica que productos locales o servicios sin variante en Shopify
     * se envíen como líneas personalizadas (custom line items con title y price).
     */
    public function test_soporta_line_items_personalizados_para_productos_sin_shopify_id(): void
    {
        $servicio = Producto::create([
            'id_empresa' => $this->empresa->id,
            'nombre' => 'Servicio de Mantenimiento',
            'precio' => 45.00,
        ]);

        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'estado' => 'Pagada',
            'total' => 45.00,
        ]);

        Detalle::create([
            'id_venta' => $venta->id,
            'id_producto' => $servicio->id,
            'cantidad' => 1,
            'precio' => 45.00,
            'total' => 45.00,
        ]);

        $venta->load(['detalles.producto', 'cliente']);

        $payload = $this->orderService->construirPayloadOrden($venta, $this->empresa);

        $this->assertNotNull($payload);
        $this->assertCount(1, $payload['line_items']);
        $this->assertSame('Servicio de Mantenimiento', $payload['line_items'][0]['title']);
        $this->assertArrayNotHasKey('variant_id', $payload['line_items'][0]);
        $this->assertSame('45.00', $payload['line_items'][0]['price']);
    }

    /**
     * Verifica que si la bodega de la venta tiene una ubicación mapeada en shopify_locations,
     * se asigne el location_id correspondiente en el payload.
     */
    public function test_mapea_location_id_segun_bodega_en_shopify_locations(): void
    {
        ShopifyLocation::create([
            'id_empresa' => $this->empresa->id,
            'shopify_location_id' => 654321098,
            'id_bodega' => 55,
            'sincronizar_stock' => true,
        ]);

        $producto = Producto::create([
            'id_empresa' => $this->empresa->id,
            'nombre' => 'Audífonos Bluetooth',
            'shopify_variant_id' => 112233,
            'precio' => 20.00,
        ]);

        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'id_bodega' => 55,
            'estado' => 'Pagada',
            'total' => 20.00,
        ]);

        Detalle::create([
            'id_venta' => $venta->id,
            'id_producto' => $producto->id,
            'cantidad' => 1,
            'precio' => 20.00,
            'total' => 20.00,
        ]);

        $venta->load(['detalles.producto', 'cliente']);

        $payload = $this->orderService->construirPayloadOrden($venta, $this->empresa);

        $this->assertSame(654321098, $payload['location_id']);
    }

    /**
     * Verifica que el Job SincronizarVentaAShopifyJob invoque correctamente al servicio.
     */
    public function test_job_sincronizar_venta_a_shopify_invoca_order_service(): void
    {
        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'estado' => 'Pagada',
            'total' => 15.00,
        ]);

        $orderServiceMock = $this->createMock(ShopifyOrderService::class);
        $orderServiceMock
            ->expects($this->once())
            ->method('crearOrdenDesdeVenta')
            ->with($this->callback(fn ($arg) => $arg->id === $venta->id));

        $job = new SincronizarVentaAShopifyJob($venta->id);
        $job->handle($orderServiceMock);
    }

    /**
     * Verifica que marcarOrdenPagadaEnShopify envíe la transacción de pago exitosamente a Shopify.
     */
    public function test_marcar_orden_pagada_en_shopify_exitoso(): void
    {
        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'estado' => 'Pagada',
            'referencia_shopify' => 'SHOPIFY-7788990011',
            'total' => 25.50,
            'forma_pago' => 'Efectivo',
        ]);

        $this->apiClientMock
            ->expects($this->once())
            ->method('post')
            ->with(
                'orders/7788990011/transactions.json',
                $this->callback(function ($payload) {
                    return isset($payload['transaction'])
                        && $payload['transaction']['kind'] === 'sale'
                        && $payload['transaction']['status'] === 'success'
                        && $payload['transaction']['amount'] === '25.50'
                        && $payload['transaction']['gateway'] === 'efectivo'
                        && $payload['transaction']['source'] === 'external';
                })
            )
            ->willReturn([
                'status' => 'success',
                'body' => ['transaction' => ['id' => 11223344, 'status' => 'success']],
                'http_status' => 201,
            ]);

        $exito = $this->orderService->marcarOrdenPagadaEnShopify($venta);
        $this->assertTrue($exito);
    }

    /**
     * Verifica que si el switch shopify_sync_ventas está apagado, se omite el registro de pago.
     */
    public function test_marcar_orden_pagada_omite_si_switch_desactivado(): void
    {
        $this->empresa->update(['shopify_sync_ventas' => false]);

        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'estado' => 'Pagada',
            'referencia_shopify' => 'SHOPIFY-7788990011',
            'total' => 25.50,
        ]);

        $this->apiClientMock->expects($this->never())->method('post');

        $exito = $this->orderService->marcarOrdenPagadaEnShopify($venta);
        $this->assertFalse($exito);
    }

    /**
     * Verifica que ventas sin referencia_shopify omitan la sincronización de pago.
     */
    public function test_marcar_orden_pagada_omite_sin_referencia_shopify(): void
    {
        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'estado' => 'Pagada',
            'referencia_shopify' => null,
            'total' => 25.50,
        ]);

        $this->apiClientMock->expects($this->never())->method('post');

        $exito = $this->orderService->marcarOrdenPagadaEnShopify($venta);
        $this->assertFalse($exito);
    }

    /**
     * Verifica que SincronizarPagoVentaAShopifyJob ejecute marcarOrdenPagadaEnShopify en el servicio.
     */
    public function test_job_sincronizar_pago_venta_invoca_order_service(): void
    {
        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'estado' => 'Pagada',
            'referencia_shopify' => 'SHOPIFY-7788990011',
            'total' => 50.00,
        ]);

        $orderServiceMock = $this->createMock(ShopifyOrderService::class);
        $orderServiceMock
            ->expects($this->once())
            ->method('marcarOrdenPagadaEnShopify')
            ->with($this->callback(fn ($arg) => $arg->id === $venta->id))
            ->willReturn(true);

        $job = new SincronizarPagoVentaAShopifyJob($venta->id);
        $job->handle($orderServiceMock);
    }

    /**
     * Verifica que ShopifyVentaObserver despache el job SincronizarPagoVentaAShopifyJob
     * cuando una venta con referencia_shopify pasa de Pendiente a Pagada.
     */
    public function test_observer_dispara_job_pago_cuando_venta_pasa_a_pagada(): void
    {
        Queue::fake();

        $venta = Venta::create([
            'id_empresa' => $this->empresa->id,
            'estado' => 'Pendiente',
            'referencia_shopify' => 'SHOPIFY-8899001122',
            'total' => 75.00,
        ]);

        $venta->estado = 'Pagada';
        $venta->save();

        Queue::assertPushed(SincronizarPagoVentaAShopifyJob::class, function ($job) use ($venta) {
            return $job->ventaId === $venta->id;
        });
    }
}
