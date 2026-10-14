<?php

namespace Tests\Unit\Services\Contabilidad;

use App\Services\Contabilidad\LibroConsumidoresFilas;
use PHPUnit\Framework\TestCase;

class LibroConsumidoresFilasTest extends TestCase
{
    public function test_correlativo_sale_de_codigo_generacion_sin_leer_dte(): void
    {
        $filas = LibroConsumidoresFilas::filas([
            $this->venta('2026-10-01', '10', 'UUID-B', 'Factura', 113, 100),
            $this->venta('2026-10-01', '2', 'UUID-A', 'Factura', 56.5, 50),
            $this->venta('2026-10-02', '1', 'UUID-C', 'Factura de exportación', 80, 80),
        ], true);

        $this->assertCount(2, $filas);
        $this->assertSame('UUID-A', $filas[0]['correlativo_inicial']);
        $this->assertSame('UUID-B', $filas[0]['correlativo_final']);
        $this->assertSame(150.0, $filas[0]['ventas_internas_gravadas']);
        $this->assertSame(0.0, $filas[0]['exportaciones']);
        $this->assertSame(169.5, $filas[0]['total_ventas_diarias_propias']);
        $this->assertSame('UUID-C', $filas[1]['correlativo_inicial']);
        $this->assertSame(80.0, $filas[1]['exportaciones']);
        $this->assertSame(0.0, $filas[1]['ventas_internas_gravadas']);
    }

    public function test_sin_facturacion_electronica_usa_correlativo(): void
    {
        $filas = LibroConsumidoresFilas::filas([
            $this->venta('2026-10-01', '20', 'UUID-Z', 'Factura', 10, 10),
        ], false);

        $this->assertSame('20', $filas[0]['correlativo_inicial']);
        $this->assertSame('20', $filas[0]['correlativo_final']);
    }

    private function venta(string $fecha, string $correlativo, string $codigo, string $documento, float $total, float $gravada): object
    {
        return (object) [
            'fecha' => $fecha,
            'correlativo' => $correlativo,
            'codigo_generacion' => $codigo,
            'documento_nombre' => $documento,
            'total' => $total,
            'cuenta_a_terceros' => 0,
            'exenta' => 0,
            'gravada' => $gravada,
            'sub_total' => $gravada,
            'no_sujeta' => 0,
            'iva' => round($total - $gravada, 2),
            'dte' => 'no-leer',
        ];
    }
}
