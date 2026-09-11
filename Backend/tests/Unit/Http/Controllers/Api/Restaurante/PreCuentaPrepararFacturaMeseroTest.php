<?php

namespace Tests\Unit\Http\Controllers\Api\Restaurante;

use App\Http\Controllers\Api\Restaurante\PreCuentaController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PreCuentaPrepararFacturaMeseroTest extends TestCase
{
    public function test_preparar_factura_incluye_mesero_y_su_canal(): void
    {
        $ref = new ReflectionMethod(PreCuentaController::class, 'prepararFactura');
        $file = file($ref->getFileName());
        $source = implode('', array_slice($file, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1));

        $this->assertStringContainsString("'mesero_id'", $source);
        $this->assertStringContainsString('usuario_id', $source);
        $this->assertStringContainsString("'mesero_id_canal'", $source);
        $this->assertStringContainsString('id_canal', $source);
    }
}
