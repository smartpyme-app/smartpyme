<?php

namespace Tests\Unit\Services\Bonos;

use App\Models\Bonos\BonoGenerado;
use App\Services\Bonos\BonoGeneradoService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BonoGeneradoServiceTest extends TestCase
{
    public function test_aprobar_rechaza_si_no_pendiente(): void
    {
        $bono = new BonoGenerado([
            'id' => 1,
            'id_empresa' => 1,
            'estado' => BonoGenerado::ESTADO_APROBADO,
        ]);

        $service = new BonoGeneradoService(
            findForUpdate: fn () => $bono,
            persist: fn () => $this->fail('No debe persistir'),
        );

        $this->expectException(ValidationException::class);
        $service->aprobar(1, 1, 99);
    }

    public function test_pagar_rechaza_si_no_aprobado(): void
    {
        $bono = new BonoGenerado([
            'id' => 1,
            'id_empresa' => 1,
            'estado' => BonoGenerado::ESTADO_PENDIENTE,
        ]);

        $service = new BonoGeneradoService(
            findForUpdate: fn () => $bono,
            persist: fn () => $this->fail('No debe persistir'),
        );

        $this->expectException(ValidationException::class);
        $service->pagar(1, 1);
    }
}
