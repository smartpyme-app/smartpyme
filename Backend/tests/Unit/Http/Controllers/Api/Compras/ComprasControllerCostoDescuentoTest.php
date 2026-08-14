<?php

namespace Tests\Unit\Http\Controllers\Api\Compras;

use App\Http\Controllers\Api\Compras\ComprasController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ComprasControllerCostoDescuentoTest extends TestCase
{
    public function test_al_guardar_compra_el_costo_de_producto_usa_neto_con_descuento(): void
    {
        $source = $this->methodSource(ComprasController::class, 'facturacion');

        $this->assertNotFalse(
            strpos($source, 'calcularCostoUnitarioNetoBase'),
            'Debe calcular el costo de inventario con el neto pagado (incluye descuento)'
        );
        $this->assertNotFalse(
            strpos($source, "\$det['descuento']"),
            'Debe restar el descuento de línea al actualizar costo y costo promedio'
        );
        $this->assertFalse(
            (bool) preg_match('/\$det\[\'cantidad\'\]\s*\*\s*\$det\[\'costo\'\]\s*;/', $source),
            'No debe usar cantidad × costo sin descuento como subtotal de inventario'
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
