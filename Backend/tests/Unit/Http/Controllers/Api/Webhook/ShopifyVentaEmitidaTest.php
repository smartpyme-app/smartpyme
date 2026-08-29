<?php

namespace Tests\Unit\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Api\Webhook\ShopifyController;
use App\Models\Ventas\Venta;
use App\Services\ImpuestosService;
use App\Services\ShippingService;
use App\Services\Shopify\ShopifyClienteService;
use App\Services\Shopify\ShopifyVentaService;
use App\Services\ShopifyImageService;
use App\Services\ShopifySyncCache;
use App\Services\ShopifyTransformer;
use ReflectionClass;
use Tests\TestCase;

class ShopifyVentaEmitidaTest extends TestCase
{
    private ShopifyController $controller;
    private $methodVentaEmitida;

    protected function setUp(): void
    {
        parent::setUp();

        $transformer = $this->createMock(ShopifyTransformer::class);
        $cache = $this->createMock(ShopifySyncCache::class);
        $shippingService = $this->createMock(ShippingService::class);
        $impuestosService = $this->createMock(ImpuestosService::class);

        $this->controller = new ShopifyController(
            $transformer,
            $cache,
            $shippingService,
            $impuestosService,
            $this->createMock(ShopifyVentaService::class),
            $this->createMock(ShopifyClienteService::class),
            $this->createMock(ShopifyImageService::class)
        );

        $reflector = new ReflectionClass(ShopifyController::class);
        $this->methodVentaEmitida = $reflector->getMethod('ventaEmitida');
        $this->methodVentaEmitida->setAccessible(true);
    }

    public function test_venta_con_sello_mh_es_considerada_emitida(): void
    {
        $venta = new Venta([
            'sello_mh' => 'SELLO-MH-123',
            'codigo_generacion' => 'UUID-GENERACION',
        ]);

        $resultado = $this->methodVentaEmitida->invoke($this->controller, $venta);

        $this->assertTrue($resultado);
    }

    public function test_venta_sin_sello_mh_no_es_considerada_emitida(): void
    {
        $venta = new Venta([
            'sello_mh' => null,
            'codigo_generacion' => null,
        ]);

        $resultado = $this->methodVentaEmitida->invoke($this->controller, $venta);

        $this->assertFalse($resultado);
    }

    public function test_venta_con_sello_mh_vacio_no_es_considerada_emitida(): void
    {
        $venta = new Venta([
            'sello_mh' => '',
        ]);

        $resultado = $this->methodVentaEmitida->invoke($this->controller, $venta);

        $this->assertFalse($resultado);
    }
}
