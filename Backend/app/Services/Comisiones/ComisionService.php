<?php

namespace App\Services\Comisiones;

use App\Models\Admin\EmpresaFuncionalidad;
use App\Models\Comisiones\ComisionMovimiento;
use App\Models\Ventas\Devoluciones\Devolucion;
use App\Models\Ventas\Venta;
use App\Services\Funcionalidades\FuncionalidadAccess;
use Carbon\Carbon;
use Closure;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;

class ComisionService
{
    private const SLUG_COMISIONES = 'comisiones-vendedores';
    private const SLUG_GIFT_CARDS = 'gift-cards';

    /** @var Closure(int, string): bool */
    private Closure $tieneFuncionalidad;

    /** @var Closure(int): array<string, mixed> */
    private Closure $obtenerConfigComisiones;

    /** @var Closure(int): array<string, mixed> */
    private Closure $obtenerConfigGiftCards;

    /** @var Closure(array<string, mixed>, array<string, mixed>): object */
    private Closure $persistirMovimiento;

    /** @var Closure(array<string, mixed>, array<string, mixed>): object */
    private Closure $persistirAjuste;

    /**
     * @param  Closure(int, string): bool|null  $tieneFuncionalidad
     * @param  Closure(int): array<string, mixed>|null  $obtenerConfigComisiones
     * @param  Closure(array<string, mixed>, array<string, mixed>): object|null  $persistirMovimiento
     * @param  Closure(array<string, mixed>, array<string, mixed>): object|null  $persistirAjuste
     * @param  Closure(int): array<string, mixed>|null  $obtenerConfigGiftCards
     */
    public function __construct(
        private ComisionPeriodoService $periodoService,
        private ComisionPorcentajeResolver $resolver,
        private ComisionBaseCalculator $calculator,
        ?Closure $tieneFuncionalidad = null,
        ?Closure $obtenerConfigComisiones = null,
        ?Closure $persistirMovimiento = null,
        ?Closure $persistirAjuste = null,
        ?Closure $obtenerConfigGiftCards = null
    ) {
        $this->tieneFuncionalidad = $tieneFuncionalidad
            ?? fn (int $idEmpresa, string $slug) => FuncionalidadAccess::empresaTieneSlug($idEmpresa, $slug);
        $this->obtenerConfigComisiones = $obtenerConfigComisiones
            ?? fn (int $idEmpresa) => self::configFuncionalidad($idEmpresa, self::SLUG_COMISIONES);
        $this->obtenerConfigGiftCards = $obtenerConfigGiftCards
            ?? fn (int $idEmpresa) => self::configFuncionalidad($idEmpresa, self::SLUG_GIFT_CARDS);
        $this->persistirMovimiento = $persistirMovimiento
            ?? fn (array $where, array $values) => ComisionMovimiento::withoutGlobalScope('empresa')
                ->firstOrCreate($where, $values);
        $this->persistirAjuste = $persistirAjuste
            ?? fn (array $where, array $values) => ComisionMovimiento::withoutGlobalScope('empresa')
                ->updateOrCreate($where, $values);
    }

    public function registrarVentaPagada(object $venta): void
    {
        if (! ($this->tieneFuncionalidad)((int) $venta->id_empresa, self::SLUG_COMISIONES)) {
            return;
        }

        $config = ($this->obtenerConfigComisiones)((int) $venta->id_empresa);
        $baseCalculo = $config['base_calculo'] ?? 'subtotal_sin_iva';
        $fechaEvento = $venta->fecha_pago ?? $venta->fecha ?? now();
        $periodo = $this->periodoService->periodoParaFecha((int) $venta->id_empresa, Carbon::parse($fechaEvento));

        foreach ($venta->detalles as $detalle) {
            $this->registrarLineaVenta(
                (int) $venta->id_empresa,
                (int) $venta->id,
                $detalle,
                (int) ($venta->id_vendedor ?? 0),
                $baseCalculo,
                $periodo,
                $fechaEvento,
                ComisionMovimiento::ORIGEN_VENTA,
                null
            );
        }
    }

    public function registrarAjustePorDevolucion(
        object $movimientoOriginal,
        float $montoBaseDevueltoAcumulado,
        bool $anulacionCompleta = false,
        ?DateTimeInterface $fechaEvento = null
    ): ?object {
        if (! ($this->tieneFuncionalidad)((int) $movimientoOriginal->id_empresa, self::SLUG_COMISIONES)) {
            return null;
        }

        $originalBase = (float) $movimientoOriginal->monto_base;
        if ($originalBase <= 0) {
            return null;
        }

        $ratio = $anulacionCompleta
            ? 1.0
            : min(1.0, $montoBaseDevueltoAcumulado / $originalBase);

        $montoBase = round(-$originalBase * $ratio, 4);
        $montoComision = round(-((float) $movimientoOriginal->monto_comision) * $ratio, 4);

        if ($montoComision === 0.0 && $montoBase === 0.0) {
            return null;
        }

        $fecha = $fechaEvento ?? $movimientoOriginal->fecha_evento ?? now();
        $periodo = $this->periodoService->periodoParaAjuste($movimientoOriginal);

        $where = [
            'id_empresa' => (int) $movimientoOriginal->id_empresa,
            'origen' => ComisionMovimiento::ORIGEN_AJUSTE_DEVOLUCION,
            'id_movimiento_origen' => (int) $movimientoOriginal->id,
        ];

        $values = [
            'id_vendedor' => (int) $movimientoOriginal->id_vendedor,
            'id_periodo' => $periodo->id,
            'id_venta' => $movimientoOriginal->id_venta,
            'id_detalle_venta' => $movimientoOriginal->id_detalle_venta,
            'id_categoria' => $movimientoOriginal->id_categoria,
            'id_subcategoria' => $movimientoOriginal->id_subcategoria,
            'monto_base' => $montoBase,
            'porcentaje_aplicado' => (float) $movimientoOriginal->porcentaje_aplicado,
            'monto_comision' => $montoComision,
            'fecha_evento' => $fecha,
        ];

        return ($this->persistirAjuste)($where, $values);
    }

    public function ajustarPorAnulacionVenta(int $idVenta, ?DateTimeInterface $fechaEvento = null): void
    {
        $movimientos = ComisionMovimiento::withoutGlobalScope('empresa')
            ->where('id_venta', $idVenta)
            ->where('origen', ComisionMovimiento::ORIGEN_VENTA)
            ->get();

        if ($movimientos->isEmpty()) {
            return;
        }

        if (! ($this->tieneFuncionalidad)((int) $movimientos->first()->id_empresa, self::SLUG_COMISIONES)) {
            return;
        }

        $fecha = $fechaEvento ?? now();

        foreach ($movimientos as $movimiento) {
            $this->registrarAjustePorDevolucion(
                $movimiento,
                (float) $movimiento->monto_base,
                true,
                $fecha
            );
        }
    }

    public function syncAjustesPorDevolucion(object $devolucion): void
    {
        if (($devolucion->tipo ?? null) === 'descuento_ajuste') {
            return;
        }

        if (! ($this->tieneFuncionalidad)((int) $devolucion->id_empresa, self::SLUG_COMISIONES)) {
            return;
        }

        $idVenta = (int) $devolucion->id_venta;
        $venta = Venta::with('detalles')->find($idVenta);
        if ($venta === null) {
            return;
        }

        $config = ($this->obtenerConfigComisiones)((int) $devolucion->id_empresa);
        $baseCalculo = $config['base_calculo'] ?? 'subtotal_sin_iva';

        $devolucionesActivas = Devolucion::withoutGlobalScope('empresa')
            ->where('id_venta', $idVenta)
            ->where('enable', true)
            ->where('tipo', '!=', 'descuento_ajuste')
            ->with('detalles')
            ->get();

        $movimientos = ComisionMovimiento::withoutGlobalScope('empresa')
            ->where('id_venta', $idVenta)
            ->where('origen', ComisionMovimiento::ORIGEN_VENTA)
            ->get();

        if ($movimientos->isEmpty()) {
            return;
        }

        $detallesPorId = $venta->detalles->keyBy('id');
        $cantidadPorProducto = $venta->detalles
            ->groupBy('id_producto')
            ->map(fn ($grupo) => (float) $grupo->sum('cantidad'));

        $baseDevueltaPorProducto = [];
        foreach ($devolucionesActivas as $devolucionActiva) {
            foreach ($devolucionActiva->detalles as $detalleDevolucion) {
                $idProducto = (int) $detalleDevolucion->id_producto;
                $baseDevueltaPorProducto[$idProducto] = ($baseDevueltaPorProducto[$idProducto] ?? 0.0)
                    + $this->calculator->calcular($detalleDevolucion, $baseCalculo);
            }
        }

        $fechaEvento = $devolucion->fecha ?? now();

        foreach ($movimientos as $movimiento) {
            $detalleVenta = $detallesPorId->get($movimiento->id_detalle_venta);
            if ($detalleVenta === null) {
                continue;
            }

            $idProducto = (int) $detalleVenta->id_producto;
            $cantidadTotalProducto = $cantidadPorProducto[$idProducto] ?? 0.0;
            if ($cantidadTotalProducto <= 0) {
                continue;
            }

            $participacionLinea = (float) $detalleVenta->cantidad / $cantidadTotalProducto;
            $baseDevueltaProducto = (float) ($baseDevueltaPorProducto[$idProducto] ?? 0.0);
            $montoBaseDevueltoAcumulado = min(
                (float) $movimiento->monto_base,
                round($baseDevueltaProducto * $participacionLinea, 4)
            );

            if ($montoBaseDevueltoAcumulado <= 0) {
                ComisionMovimiento::withoutGlobalScope('empresa')
                    ->where('id_empresa', (int) $movimiento->id_empresa)
                    ->where('origen', ComisionMovimiento::ORIGEN_AJUSTE_DEVOLUCION)
                    ->where('id_movimiento_origen', (int) $movimiento->id)
                    ->delete();

                continue;
            }

            $this->registrarAjustePorDevolucion(
                $movimiento,
                $montoBaseDevueltoAcumulado,
                false,
                $fechaEvento
            );
        }
    }

    public function registrarDesdeRedencion(
        int $idEmpresa,
        int $idVendedor,
        int $idVenta,
        int $idDetalleVenta,
        int $idGiftCardRedencion,
        ?int $idCategoria,
        ?int $idSubcategoria,
        object $detalleLinea,
        DateTimeInterface $fechaEvento
    ): ?ComisionMovimiento {
        if (! ($this->tieneFuncionalidad)($idEmpresa, self::SLUG_COMISIONES)) {
            return null;
        }

        if ($this->esCategoriaGiftCard($idEmpresa, $idCategoria)) {
            return null;
        }

        $pct = $this->resolver->resolver($idEmpresa, $idCategoria, $idSubcategoria);
        if ($pct == 0.0) {
            return null;
        }

        $config = ($this->obtenerConfigComisiones)($idEmpresa);
        $baseCalculo = $config['base_calculo'] ?? 'subtotal_sin_iva';
        $base = $this->calculator->calcular($detalleLinea, $baseCalculo);
        $montoComision = round($base * ($pct / 100), 4);
        $periodo = $this->periodoService->periodoParaFecha($idEmpresa, Carbon::parse($fechaEvento));

        $where = [
            'id_empresa' => $idEmpresa,
            'id_gift_card_redencion' => $idGiftCardRedencion,
        ];

        $values = [
            'id_vendedor' => $idVendedor,
            'id_periodo' => $periodo->id,
            'origen' => ComisionMovimiento::ORIGEN_REDENCION_GIFT_CARD,
            'id_venta' => $idVenta,
            'id_detalle_venta' => $idDetalleVenta,
            'id_categoria' => $idCategoria,
            'id_subcategoria' => $idSubcategoria,
            'monto_base' => $base,
            'porcentaje_aplicado' => $pct,
            'monto_comision' => $montoComision,
            'fecha_evento' => $fechaEvento,
        ];

        return ComisionMovimiento::withoutGlobalScope('empresa')->firstOrCreate($where, $values);
    }

    /**
     * @param  object  $detalle
     */
    private function registrarLineaVenta(
        int $idEmpresa,
        int $idVenta,
        $detalle,
        int $idVendedorVenta,
        string $baseCalculo,
        $periodo,
        $fechaEvento,
        string $origen,
        ?int $idGiftCardRedencion
    ): ?ComisionMovimiento {
        $producto = $detalle->producto ?? null;
        $idCategoria = $producto ? (int) ($producto->id_categoria ?? 0) : null;
        $idSubcategoria = $producto && ! empty($producto->subcategoria_id)
            ? (int) $producto->subcategoria_id
            : null;

        if ($idCategoria === 0) {
            $idCategoria = null;
        }

        if ($this->esCategoriaGiftCard($idEmpresa, $idCategoria)) {
            return null;
        }

        $pct = $this->resolver->resolver($idEmpresa, $idCategoria, $idSubcategoria);
        if ($pct == 0.0) {
            return null;
        }

        $idVendedor = $this->vendedorEfectivo(
            isset($detalle->id_vendedor) ? (int) $detalle->id_vendedor : null,
            $idVendedorVenta
        );

        if ($idVendedor === null) {
            Log::info('ComisionService: línea sin vendedor efectivo, se omite', [
                'id_venta' => $idVenta,
                'id_detalle_venta' => $detalle->id ?? null,
            ]);

            return null;
        }

        $base = $this->calculator->calcular($detalle, $baseCalculo);
        $montoComision = round($base * ($pct / 100), 4);

        $where = [
            'id_empresa' => $idEmpresa,
            'origen' => $origen,
            'id_detalle_venta' => (int) $detalle->id,
        ];

        $values = [
            'id_vendedor' => $idVendedor,
            'id_periodo' => $periodo->id,
            'id_venta' => $idVenta,
            'id_gift_card_redencion' => $idGiftCardRedencion,
            'id_categoria' => $idCategoria,
            'id_subcategoria' => $idSubcategoria,
            'monto_base' => $base,
            'porcentaje_aplicado' => $pct,
            'monto_comision' => $montoComision,
            'fecha_evento' => $fechaEvento,
        ];

        return ($this->persistirMovimiento)($where, $values);
    }

    private function esCategoriaGiftCard(int $idEmpresa, ?int $idCategoria): bool
    {
        if ($idCategoria === null) {
            return false;
        }

        $config = ($this->obtenerConfigComisiones)($idEmpresa);
        $excluir = $config['excluir_categoria_gift_cards'] ?? true;
        if (! $excluir) {
            return false;
        }

        $idGiftComisiones = $config['id_categoria_gift_cards'] ?? null;
        if ($idGiftComisiones !== null && (int) $idGiftComisiones === $idCategoria) {
            return true;
        }

        $giftConfig = ($this->obtenerConfigGiftCards)($idEmpresa);
        $idGiftCards = $giftConfig['id_categoria_gift_cards'] ?? null;

        return $idGiftCards !== null && (int) $idGiftCards === $idCategoria;
    }

    private function vendedorEfectivo(?int $detalleVendedor, ?int $ventaVendedor): ?int
    {
        $v = $detalleVendedor !== null && $detalleVendedor !== 0 ? $detalleVendedor : null;

        if ($v !== null) {
            return $v;
        }

        if ($ventaVendedor !== null && $ventaVendedor !== 0) {
            return $ventaVendedor;
        }

        return null;
    }

    /** @return array<string, mixed> */
    private static function configFuncionalidad(int $idEmpresa, string $slug): array
    {
        $row = EmpresaFuncionalidad::query()
            ->where('id_empresa', $idEmpresa)
            ->where('activo', true)
            ->whereHas('funcionalidad', fn ($q) => $q->where('slug', $slug))
            ->first();

        return $row?->configuracion ?? [];
    }
}
