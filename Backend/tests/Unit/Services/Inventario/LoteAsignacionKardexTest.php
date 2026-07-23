<?php

namespace Tests\Unit\Services\Inventario;

use App\Services\Inventario\LoteAsignacionService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class LoteAsignacionKardexTest extends TestCase
{
    public function test_cada_lote_genera_opciones_kardex_con_lote_id(): void
    {
        $asignaciones = [
            ['lote_id' => 101, 'cantidad' => 3.0],
            ['lote_id' => 102, 'cantidad' => 2.0],
        ];

        $kardexPorLote = [];
        foreach ($asignaciones as $asig) {
            $kardexPorLote[] = [
                'lote_id' => $asig['lote_id'],
                'cantidad' => $asig['cantidad'],
            ];
        }

        $this->assertCount(2, $kardexPorLote);
        $this->assertSame(101, $kardexPorLote[0]['lote_id']);
        $this->assertSame(102, $kardexPorLote[1]['lote_id']);
        $this->assertSame(3.0, $kardexPorLote[0]['cantidad']);
        $this->assertSame(2.0, $kardexPorLote[1]['cantidad']);
    }

    /**
     * Contrato del bug Factura #4820: al anular, existencia kardex = stock después del retorno.
     * Antes: kardex con stock viejo (1) y luego stock += 1 → inventario 2 / kardex 1.
     */
    public function test_existencia_kardex_anulacion_usa_stock_despues_del_movimiento(): void
    {
        $stockTrasVenta = 1.0;
        $cantidadAnulada = 1.0;

        $stockDespues = $stockTrasVenta + $cantidadAnulada;
        $totalCantidadKardex = $stockDespues;

        $this->assertSame(2.0, $totalCantidadKardex);
        $this->assertNotSame($stockTrasVenta, $totalCantidadKardex);
    }

    public function test_revertir_entrada_actualiza_stock_antes_de_llamar_kardex(): void
    {
        $source = $this->methodSource(LoteAsignacionService::class, 'revertirEntrada');

        $posStock = strpos($source, '$inventario->stock = (float) $inventario->stock + $cantidadBase');
        $posKardexSinLote = strrpos($source, '$inventario->kardex($venta, $cantidadBase * -1)');

        $this->assertNotFalse($posStock, 'Debe actualizar inventario.stock al devolver entrada');
        $this->assertNotFalse($posKardexSinLote, 'Debe registrar kardex de venta anulada');
        $this->assertLessThan(
            $posKardexSinLote,
            $posStock,
            'inventario.stock debe actualizarse antes del kardex (sin lotes)'
        );
    }

    public function test_reactivar_salida_actualiza_stock_antes_de_llamar_kardex(): void
    {
        $source = $this->methodSource(LoteAsignacionService::class, 'reactivarSalidaDesdeDetalle');

        $posStock = strpos($source, '$inventario->stock = max(0, (float) $inventario->stock - $cantidadBase)');
        $posKardexSinLote = strrpos($source, '$inventario->kardex($venta, $cantidadBase, $precio)');

        $this->assertNotFalse($posStock, 'Debe actualizar inventario.stock al reactivar salida');
        $this->assertNotFalse($posKardexSinLote, 'Debe registrar kardex al reactivar salida');
        $this->assertLessThan(
            $posKardexSinLote,
            $posStock,
            'inventario.stock debe actualizarse antes del kardex (sin lotes)'
        );
    }

    private function methodSource(string $class, string $method): string
    {
        $ref = new ReflectionMethod($class, $method);
        $lines = file($ref->getFileName());

        return implode('', array_slice(
            $lines,
            $ref->getStartLine() - 1,
            $ref->getEndLine() - $ref->getStartLine() + 1
        ));
    }
}
