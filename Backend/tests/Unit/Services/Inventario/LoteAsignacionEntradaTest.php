<?php

namespace Tests\Unit\Services\Inventario;

use App\Http\Controllers\Api\Compras\ComprasController;
use App\Services\Inventario\LoteAsignacionService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class LoteAsignacionEntradaTest extends TestCase
{
    public function test_resolver_asignaciones_entrada_existe_y_no_usa_fifo(): void
    {
        $source = $this->methodSource(LoteAsignacionService::class, 'resolverAsignacionesEntrada');

        $this->assertStringContainsString('validarAsignacionManual', $source);
        $this->assertStringNotContainsString('distribuirAutomatico', $source);
        $this->assertStringContainsString('false', $source);
    }

    public function test_validar_asignacion_manual_puede_omitir_chequeo_de_stock(): void
    {
        $source = $this->methodSource(LoteAsignacionService::class, 'validarAsignacionManual');

        $this->assertStringContainsString('$validarStock', $source);
        $this->assertStringContainsString('if ($validarStock', $source);
    }

    public function test_aplicar_entrada_compra_suma_stock_por_lote_y_sincroniza_detalle(): void
    {
        $source = $this->methodSource(LoteAsignacionService::class, 'aplicarEntradaCompra');

        $this->assertStringContainsString('sincronizarDetalleCompraLotes', $source);
        $this->assertStringContainsString('$lote->stock = (float) $lote->stock + $cantidad', $source);
        $this->assertStringContainsString("'lote_id' => \$asig['lote_id']", $source);
        $this->assertStringContainsString('$inventario->stock = (float) $inventario->stock + $cantidadTotal', $source);
    }

    public function test_revertir_entrada_compra_usa_detalle_compra_lotes_o_lote_id_legacy(): void
    {
        $source = $this->methodSource(LoteAsignacionService::class, 'revertirEntradaCompra');

        $this->assertStringContainsString('DetalleCompraLote', $source);
        $this->assertStringContainsString('$detalle->lote_id', $source);
        $this->assertStringContainsString('$lote->stock = max(0, (float) $lote->stock -', $source);
    }

    public function test_compras_controller_acepta_lotes_asignados_sin_lote_id(): void
    {
        $source = $this->methodSource(ComprasController::class, 'facturacion');

        $this->assertStringContainsString("lotes_asignados", $source);
        $this->assertStringContainsString('resolverAsignacionesEntrada', $source);
        $this->assertStringContainsString('aplicarEntradaCompra', $source);
        $this->assertFalse(
            (bool) preg_match("/if \(!isset\(\\\$det\['lote_id'\]\) \|\| !\\\$det\['lote_id'\]\)/", $source),
            'No debe exigir solo lote_id cuando la línea puede traer varios lotes'
        );
    }

    public function test_compras_controller_anula_usando_revertir_entrada_compra(): void
    {
        $source = $this->methodSource(ComprasController::class, 'store');

        $this->assertStringContainsString('revertirEntradaCompra', $source);
        $this->assertStringContainsString('reactivarEntradaCompra', $source);
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
