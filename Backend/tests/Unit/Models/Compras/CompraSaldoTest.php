<?php

namespace Tests\Unit\Models\Compras;

use App\Http\Controllers\Api\Compras\Devoluciones\DevolucionComprasController;
use App\Models\Compras\Compra;
use App\Services\Compras\AbonoCompraService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CompraSaldoTest extends TestCase
{
    public function test_saldo_resta_abonos_confirmados_y_devoluciones_activas(): void
    {
        $source = $this->methodSource(Compra::class, 'getSaldoAttribute');

        $this->assertNotFalse(
            strpos($source, "abonos()->where('estado', 'Confirmado')"),
            'El saldo debe restar solo abonos confirmados'
        );
        $this->assertNotFalse(
            strpos($source, "devoluciones()->where('enable', 1)"),
            'El saldo debe restar devoluciones activas, igual que el listado y Venta'
        );
    }

    public function test_abono_sincroniza_estado_de_compra_por_saldo(): void
    {
        $source = $this->methodSource(AbonoCompraService::class, 'actualizarEstadoCompra');

        $this->assertNotFalse(
            strpos($source, 'sincronizarEstadoPorSaldo'),
            'Al registrar un abono, el estado Pagada/Pendiente debe usar el saldo con devoluciones'
        );
    }

    public function test_facturacion_de_devolucion_sincroniza_estado_de_compra(): void
    {
        $source = $this->methodSource(DevolucionComprasController::class, 'facturacion');

        $this->assertNotFalse(
            strpos($source, 'sincronizarEstadoPorSaldo'),
            'Al guardar una devolución, la compra origen debe pasar a Pagada si el saldo queda en 0'
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
