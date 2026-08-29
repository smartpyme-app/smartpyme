<?php

namespace Tests\Unit\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Api\Webhook\ShopifyController;
use App\Models\Ventas\Clientes\Cliente;
use App\Services\ImpuestosService;
use App\Services\ShippingService;
use App\Services\Shopify\ShopifyClienteService;
use App\Services\Shopify\ShopifyVentaService;
use App\Services\ShopifyImageService;
use App\Services\ShopifySyncCache;
use App\Services\ShopifyTransformer;
use ReflectionClass;
use Tests\TestCase;

class ShopifyTipoDocumentoFiscalTest extends TestCase
{
    private ShopifyController $controller;
    private $methodEsClienteExtranjero;
    private $methodResolverNombreDocumentoFiscal;

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
        $this->methodEsClienteExtranjero = $reflector->getMethod('esClienteExtranjero');
        $this->methodEsClienteExtranjero->setAccessible(true);
        $this->methodResolverNombreDocumentoFiscal = $reflector->getMethod('resolverNombreDocumentoFiscal');
        $this->methodResolverNombreDocumentoFiscal->setAccessible(true);
    }

    public function test_cliente_extranjero_es_detectado(): void
    {
        $cliente = new Cliente([
            'cod_pais' => 'US',
        ]);

        $resultado = $this->methodEsClienteExtranjero->invoke($this->controller, $cliente);

        $this->assertTrue($resultado);
    }

    public function test_cliente_salvadoreno_no_es_extranjero(): void
    {
        $cliente = new Cliente([
            'cod_pais' => 'SV',
        ]);

        $resultado = $this->methodEsClienteExtranjero->invoke($this->controller, $cliente);

        $this->assertFalse($resultado);
    }

    public function test_cliente_sin_pais_no_es_extranjero(): void
    {
        $cliente = new Cliente([
            'cod_pais' => null,
        ]);

        $resultado = $this->methodEsClienteExtranjero->invoke($this->controller, $cliente);

        $this->assertFalse($resultado);
    }

    public function test_extranjero_resuelve_factura_de_exportacion(): void
    {
        $cliente = new Cliente([
            'cod_pais' => 'GT',
            'ncr' => '123456-7',
            'nit' => '0614-010190-001-1',
            'tipo_documento' => '36',
        ]);

        $resultado = $this->methodResolverNombreDocumentoFiscal->invoke($this->controller, $cliente);

        $this->assertSame('Factura de exportación', $resultado);
    }

    public function test_credito_fiscal_prevalece_sobre_factura(): void
    {
        $cliente = new Cliente([
            'cod_pais' => 'SV',
            'nit' => '0614-010190-001-1',
            'tipo_documento' => '36',
        ]);

        $resultado = $this->methodResolverNombreDocumentoFiscal->invoke($this->controller, $cliente);

        $this->assertSame('Crédito fiscal', $resultado);
    }

    public function test_consumidor_final_resuelve_factura(): void
    {
        $cliente = new Cliente([
            'cod_pais' => 'SV',
            'nit' => null,
            'ncr' => null,
        ]);

        $resultado = $this->methodResolverNombreDocumentoFiscal->invoke($this->controller, $cliente);

        $this->assertSame('Factura', $resultado);
    }
}
