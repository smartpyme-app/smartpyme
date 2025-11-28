<?php

namespace Tests\Unit\Services\Compras;

use Tests\TestCase;
use App\Services\Compras\ComprasAuthorizationService;
use App\Services\Compras\CompraService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery;

/**
 * IMPORTANTE: Estos tests NO usan RefreshDatabase ni ningún trait que afecte la base de datos.
 * Estos tests unitarios usan mocks y solo prueban lógica sin acceso a base de datos.
 */
class ComprasAuthorizationServiceTest extends TestCase
{
    protected ComprasAuthorizationService $service;
    protected CompraService $compraServiceMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear mock de CompraService
        $this->compraServiceMock = Mockery::mock(CompraService::class);
        $this->service = new ComprasAuthorizationService($this->compraServiceMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function no_requiere_autorizacion_compra_existente()
    {
        $request = new Request(['total' => 5000]);
        
        $result = $this->service->validarAutorizacionRequerida($request, 123, null);
        
        $this->assertFalse($result['requires_authorization']);
        $this->assertTrue($result['ok']);
    }

    /** @test */
    public function no_requiere_autorizacion_con_authorization_id()
    {
        $request = new Request(['total' => 5000]);
        
        $result = $this->service->validarAutorizacionRequerida($request, null, 456);
        
        $this->assertFalse($result['requires_authorization']);
        $this->assertTrue($result['ok']);
    }

    /** @test */
    public function requiere_autorizacion_monto_mayor_3000()
    {
        $request = new Request(['total' => 3500]);
        
        // Mock del método calcularTotal
        $this->compraServiceMock
            ->shouldReceive('calcularTotal')
            ->once()
            ->with($request)
            ->andReturn(3500.00);
        
        $result = $this->service->validarAutorizacionRequerida($request);
        
        $this->assertTrue($result['requires_authorization']);
        $this->assertFalse($result['ok']);
        $this->assertEquals('compras_altas', $result['authorization_type']);
        $this->assertArrayHasKey('message', $result);
        $this->assertEquals(3500.00, $result['total']);
    }

    /** @test */
    public function no_requiere_autorizacion_monto_menor_3000()
    {
        $request = new Request(['total' => 2500]);
        
        // Mock del método calcularTotal
        $this->compraServiceMock
            ->shouldReceive('calcularTotal')
            ->once()
            ->with($request)
            ->andReturn(2500.00);
        
        $result = $this->service->validarAutorizacionRequerida($request);
        
        $this->assertFalse($result['requires_authorization']);
        $this->assertTrue($result['ok']);
        $this->assertEquals(2500.00, $result['total']);
    }

    /** @test */
    public function no_requiere_autorizacion_monto_igual_3000()
    {
        $request = new Request(['total' => 3000]);
        
        // Mock del método calcularTotal
        $this->compraServiceMock
            ->shouldReceive('calcularTotal')
            ->once()
            ->with($request)
            ->andReturn(3000.00);
        
        $result = $this->service->validarAutorizacionRequerida($request);
        
        $this->assertFalse($result['requires_authorization']);
        $this->assertTrue($result['ok']);
    }

    /** @test */
    public function calcula_total_correctamente_desde_detalles()
    {
        $request = new Request([
            'detalles' => [
                ['total' => 1500.00],
                ['total' => 2000.00],
            ]
        ]);
        
        // Mock del método calcularTotal para calcular desde detalles
        $this->compraServiceMock
            ->shouldReceive('calcularTotal')
            ->once()
            ->with($request)
            ->andReturn(3500.00);
        
        $result = $this->service->validarAutorizacionRequerida($request);
        
        $this->assertTrue($result['requires_authorization']);
        $this->assertEquals(3500.00, $result['total']);
    }

    /** @test */
    public function get_monto_limite_autorizacion()
    {
        $limite = $this->service->getMontoLimiteAutorizacion();
        
        $this->assertEquals(3000.00, $limite);
    }
}

