<?php

namespace Tests\Unit\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Api\Webhook\ShopifyController;
use App\Models\Ventas\Clientes\Cliente;
use App\Services\ImpuestosService;
use App\Services\ShippingService;
use App\Services\ShopifySyncCache;
use App\Services\ShopifyTransformer;
use ReflectionClass;
use Tests\TestCase;

class ShopifyCreditoFiscalTest extends TestCase
{
    private ShopifyController $controller;
    private $methodEsClienteCreditoFiscal;

    protected function setUp(): void
    {
        parent::setUp();

        $transformer = $this->createMock(ShopifyTransformer::class);
        $cache = $this->createMock(ShopifySyncCache::class);
        $shippingService = $this->createMock(ShippingService::class);
        $impuestosService = $this->createMock(ImpuestosService::class);

        $this->controller = new ShopifyController($transformer, $cache, $shippingService, $impuestosService);

        $reflector = new ReflectionClass(ShopifyController::class);
        $this->methodEsClienteCreditoFiscal = $reflector->getMethod('esClienteCreditoFiscal');
        $this->methodEsClienteCreditoFiscal->setAccessible(true);
    }

    public function test_cliente_con_ncr_es_credito_fiscal(): void
    {
        $cliente = new Cliente([
            'ncr' => '123456-7',
            'nit' => null,
        ]);

        $resultado = $this->methodEsClienteCreditoFiscal->invoke($this->controller, $cliente);

        $this->assertTrue($resultado);
    }

    public function test_cliente_con_nit_tipo_documento_36_es_credito_fiscal(): void
    {
        $cliente = new Cliente([
            'ncr' => null,
            'nit' => '0614-010190-001-1',
            'tipo_documento' => '36',
        ]);

        $resultado = $this->methodEsClienteCreditoFiscal->invoke($this->controller, $cliente);

        $this->assertTrue($resultado);
    }

    public function test_cliente_con_nit_pero_tipo_documento_distinto_no_es_credito_fiscal(): void
    {
        $cliente = new Cliente([
            'ncr' => null,
            'nit' => '0614-010190-001-1',
            'tipo_documento' => '13', // DUI, no NIT
        ]);

        $resultado = $this->methodEsClienteCreditoFiscal->invoke($this->controller, $cliente);

        $this->assertFalse($resultado);
    }

    public function test_consumidor_final_no_es_credito_fiscal(): void
    {
        $cliente = new Cliente([
            'ncr' => null,
            'nit' => null,
            'tipo_documento' => null,
        ]);

        $resultado = $this->methodEsClienteCreditoFiscal->invoke($this->controller, $cliente);

        $this->assertFalse($resultado);
    }
}
