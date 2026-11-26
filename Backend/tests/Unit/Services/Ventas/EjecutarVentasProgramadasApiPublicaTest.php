<?php

namespace Tests\Unit\Services\Ventas;

use App\Services\Ventas\EjecutarVentasProgramadasService;
use App\Services\Ventas\GenerarVentasRecurrentesService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** El cron de programadas llama estos métodos sin reflexión: deben seguir siendo públicos. */
class EjecutarVentasProgramadasApiPublicaTest extends TestCase
{
    public function test_recurrentes_expone_api_usada_por_programadas(): void
    {
        foreach (['emitir', 'enviarFacturaAlCliente', 'ejecutarComoUsuario'] as $metodo) {
            $this->assertTrue((new ReflectionMethod(GenerarVentasRecurrentesService::class, $metodo))->isPublic(), $metodo);
        }
    }

    public function test_reclamar_atomico_es_publico(): void
    {
        $this->assertTrue((new ReflectionMethod(EjecutarVentasProgramadasService::class, 'reclamar'))->isPublic());
    }
}
