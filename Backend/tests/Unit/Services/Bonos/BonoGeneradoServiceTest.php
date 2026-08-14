<?php

namespace Tests\Unit\Services\Bonos;

use App\Models\Bonos\BonoGenerado;
use App\Models\Bonos\BonoRegla;
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

    public function test_crear_manual_rechaza_si_la_regla_no_es_cualitativa(): void
    {
        $regla = (object) [
            'id' => 10,
            'tipo' => BonoRegla::TIPO_META_FIJA,
            'alcance' => BonoRegla::ALCANCE_GLOBAL,
            'id_vendedores' => null,
        ];

        $service = new BonoGeneradoService(
            obtenerRegla: fn () => $regla,
            existeGenerado: fn () => false,
            crearGenerado: fn () => $this->fail('No debe crear'),
        );

        $this->expectException(ValidationException::class);
        $service->crearManual(1, [
            'id_regla' => 10,
            'id_vendedor' => 5,
            'periodo_inicio' => '2026-07-01',
            'periodo_fin' => '2026-07-31',
            'monto' => 80,
        ]);
    }

    public function test_crear_manual_persiste_origen_manual_pendiente(): void
    {
        $regla = (object) [
            'id' => 10,
            'tipo' => BonoRegla::TIPO_CUALITATIVO_MANUAL,
            'alcance' => BonoRegla::ALCANCE_GLOBAL,
            'id_vendedores' => null,
        ];

        $creado = null;
        $service = new BonoGeneradoService(
            obtenerRegla: fn () => $regla,
            existeGenerado: fn () => false,
            crearGenerado: function (array $payload) use (&$creado) {
                $creado = $payload;

                return new BonoGenerado($payload);
            },
        );

        $service->crearManual(1, [
            'id_regla' => 10,
            'id_vendedor' => 5,
            'periodo_inicio' => '2026-07-01',
            'periodo_fin' => '2026-07-31',
            'monto' => 80,
        ]);

        $this->assertSame(BonoGenerado::ORIGEN_MANUAL, $creado['origen']);
        $this->assertSame(BonoGenerado::ESTADO_PENDIENTE, $creado['estado']);
        $this->assertSame(80.0, $creado['monto']);
        $this->assertSame(5, $creado['id_vendedor']);
    }
}
