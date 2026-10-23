<?php

namespace Tests\Unit\Fidelizacion;

use App\Http\Controllers\Api\FidelizacionCliente\CanjePuntosController;
use App\Services\FidelizacionCliente\ConsumoPuntosService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ConsumoPuntosDisponiblesTest extends TestCase
{
    public function test_servicio_acepta_include_detalle(): void
    {
        $method = new ReflectionMethod(ConsumoPuntosService::class, 'obtenerInformacionPuntosDisponibles');
        $this->assertSame(3, $method->getNumberOfParameters());
    }

    public function test_controlador_compara_empresa_con_cast_entero(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Http/Controllers/Api/FidelizacionCliente/CanjePuntosController.php'
        );

        $this->assertStringContainsString('(int) Auth::user()->id_empresa !== (int) $empresaId', $source);
        $this->assertStringContainsString('include_detalle', $source);
    }
}
