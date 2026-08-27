<?php

namespace Tests\Unit\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\MHDTEController;
use App\Models\Ventas\Devoluciones\Devolucion;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class MHDTEControllerNotaCreditoRelationsTest extends TestCase
{
    public function test_devolucion_no_define_relacion_impuestos(): void
    {
        $this->assertFalse(
            method_exists(Devolucion::class, 'impuestos'),
            'Los tributos de la NC se toman de venta.impuestos; no hay tabla devolucion_impuestos'
        );
    }

    public function test_generar_dte_nota_credito_no_carga_impuestos_en_devolucion(): void
    {
        $source = $this->methodSource(MHDTEController::class, 'generarDTENotaCredito');

        $this->assertStringContainsString(
            "'venta.impuestos.impuesto'",
            $source,
            'Debe precargar impuestos de la venta origen'
        );

        $sinVenta = str_replace("'venta.impuestos.impuesto'", '', $source);
        $this->assertStringNotContainsString(
            "'impuestos.impuesto'",
            $sinVenta,
            'Devolucion no tiene relación impuestos; eager-load impuestos.impuesto lanza RelationNotFoundException'
        );
    }

    private function methodSource(string $class, string $method): string
    {
        $ref = new ReflectionMethod($class, $method);
        $file = file($ref->getFileName());
        $start = $ref->getStartLine() - 1;
        $length = $ref->getEndLine() - $start;

        return implode('', array_slice($file, $start, $length));
    }
}
