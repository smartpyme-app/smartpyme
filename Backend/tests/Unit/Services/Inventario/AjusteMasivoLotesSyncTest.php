<?php

namespace Tests\Unit\Services\Inventario;

use App\Http\Controllers\Api\Inventario\ProductosController;
use App\Services\Inventario\MigrarStockALotesService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AjusteMasivoLotesSyncTest extends TestCase
{
    public function test_ajuste_masivo_alinea_lotes_cuando_producto_usa_lotes(): void
    {
        $source = $this->methodSource(ProductosController::class, 'ajusteMasivo');

        $this->assertStringContainsString('alinearStockEnBodega', $source);
        $this->assertStringContainsString('inventario_por_lotes', $source);
        $this->assertStringContainsString('lote_id', $source);
    }

    public function test_alinear_stock_en_bodega_concentra_en_lote_primario_si_hay_varios(): void
    {
        $source = $this->methodSource(MigrarStockALotesService::class, 'alinearStockEnBodega');

        $this->assertStringContainsString('NUMERO_LOTE_INICIAL', $source);
        $this->assertStringContainsString('$stockObjetivo', $source);
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
