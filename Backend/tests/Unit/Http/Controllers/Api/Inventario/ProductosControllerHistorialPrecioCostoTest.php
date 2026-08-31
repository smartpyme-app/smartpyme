<?php

namespace Tests\Unit\Http\Controllers\Api\Inventario;

use App\Http\Controllers\Api\Inventario\ProductosController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ProductosControllerHistorialPrecioCostoTest extends TestCase
{
    public function test_el_historial_incluye_compras_pendientes_y_usa_el_servicio(): void
    {
        $source = $this->methodSource(ProductosController::class, 'historialPrecioCosto');

        $this->assertNotFalse(
            strpos($source, "whereIn('estado', ['Pagada', 'Pendiente'])"),
            'Debe incluir compras Pagada y Pendiente (crédito) que sí actualizan el costo'
        );
        $this->assertNotFalse(
            strpos($source, 'HistorialPrecioCostoService'),
            'Debe delegar el cálculo de tendencia y costo neto al servicio'
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
