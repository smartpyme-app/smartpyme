<?php

namespace Tests\Unit\Fidelizacion;

use PHPUnit\Framework\TestCase;

class ResetFidelizacionEmpresaCommandTest extends TestCase
{
    public function test_comando_declara_reset_sin_eliminar_ventas(): void
    {
        $path = dirname(__DIR__, 3) . '/app/Console/Commands/ResetFidelizacionEmpresaCommand.php';
        $source = (string) file_get_contents($path);

        $this->assertStringContainsString('fidelizacion:reset-empresa', $source);
        $this->assertStringContainsString('--dry-run', $source);
        $this->assertStringContainsString("'puntos_ganados' => null", $source);
        $this->assertStringContainsString('getEmpresasLicenciaIds', $source);
        $this->assertStringContainsString('whereIn(\'id_empresa\', $empresaIds)', $source);

        $this->assertDoesNotMatchRegularExpression(
            '/ventas[^;]*->delete\s*\(/i',
            $source,
            'No debe eliminar filas de ventas'
        );
    }
}
