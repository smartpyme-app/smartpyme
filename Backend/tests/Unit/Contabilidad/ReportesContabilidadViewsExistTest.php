<?php

namespace Tests\Unit\Contabilidad;

use PHPUnit\Framework\TestCase;

/**
 * Los PDF/Excel de reportes de contabilidad fallan con View [..] not found
 * si estas plantillas no están en el deploy (p. ej. omitidas en un merge).
 */
class ReportesContabilidadViewsExistTest extends TestCase
{
    /** @return list<string> */
    private function requiredViews(): array
    {
        return [
            'reportes/contabilidad/libro_diario.blade.php',
            'reportes/contabilidad/libro_diario_auxiliar.blade.php',
            'reportes/contabilidad/libro_diario_mayor.blade.php',
            'reportes/contabilidad/libro_mayor.blade.php',
            'reportes/contabilidad/rep_balance_comprobacion.blade.php',
            'reportes/contabilidad/balance_comprobacion.blade.php',
            'reportes/contabilidad/balance_comprobacion_mensual.blade.php',
            'reportes/contabilidad/movimiento_cuenta.blade.php',
            'reportes/contabilidad/balance_general.blade.php',
            'reportes/contabilidad/estado_resultados.blade.php',
            'reportes/contabilidad/libro-de-bancos.blade.php',
            'reportes/contabilidad/excel/libro_diario_excel.blade.php',
            'reportes/contabilidad/excel/libro_diario_auxiliar_excel.blade.php',
            'reportes/contabilidad/excel/libro_diario_mayor_excel.blade.php',
            'reportes/contabilidad/excel/balance_comprobacion_excel.blade.php',
        ];
    }

    public function test_plantillas_de_reportes_existen_en_disco(): void
    {
        $viewsRoot = dirname(__DIR__, 3) . '/resources/views/';

        foreach ($this->requiredViews() as $relative) {
            $this->assertFileExists(
                $viewsRoot . $relative,
                "Falta la vista {$relative} (el PDF/Excel de reportes no puede generarse)."
            );
        }
    }
}
