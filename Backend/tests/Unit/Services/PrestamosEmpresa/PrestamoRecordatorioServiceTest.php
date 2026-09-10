<?php

namespace Tests\Unit\Services\PrestamosEmpresa;

use App\Models\PrestamosEmpresa\PrestamoCuota;
use App\Services\PrestamosEmpresa\PrestamoRecordatorioService;
use Tests\TestCase;

class PrestamoRecordatorioServiceTest extends TestCase
{
    public function test_descripcion_cuota_incluye_datos_clave(): void
    {
        $cuota = new PrestamoCuota([
            'numero' => 3,
            'total' => 150.50,
            'fecha_vencimiento' => '2026-09-10',
        ]);
        $cuota->setRelation('prestamo', (object) ['acreedor' => 'Banco XYZ']);

        $texto = (new PrestamoRecordatorioService())->descripcionCuota($cuota);

        $this->assertStringContainsString('Cuota #3', $texto);
        $this->assertStringContainsString('Banco XYZ', $texto);
        $this->assertStringContainsString('150.50', $texto);
        $this->assertStringContainsString('10/09/2026', $texto);
    }

    public function test_cache_key_correo_empresa_es_estable_por_dia(): void
    {
        $service = new PrestamoRecordatorioService();
        $key = $service->cacheKeyCorreoEmpresa(324, 2);

        $this->assertStringStartsWith('recordatorio_prestamo_empresa_324_d2_', $key);
    }
}
