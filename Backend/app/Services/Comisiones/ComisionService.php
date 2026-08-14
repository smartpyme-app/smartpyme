<?php

namespace App\Services\Comisiones;

use App\Models\Admin\EmpresaFuncionalidad;
use App\Models\Comisiones\ComisionMovimiento;
use App\Models\Comisiones\ComisionPeriodo;
use App\Models\Comisiones\ComisionRegla;
use App\Models\Indicador;
use App\Models\Ventas\Devoluciones\Devolucion;
use App\Models\Ventas\Venta;
use App\Services\Comisiones\Calculators\ComisionCalculoResultado;
use App\Services\Comisiones\Calculators\ComisionCalculatorFactory;
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

    /** @var Closure(int): \Illuminate\Support\Collection<int, ComisionMovimiento> */
    private Closure $obtenerMovimientosVenta;

    /** @var Closure(int): ?Venta */
    private Closure $obtenerVentaConDetalles;

    /** @var Closure(int): \Illuminate\Support\Collection<int, Devolucion> */
    private Closure $obtenerDevolucionesActivas;

    /** @var Closure(int, int): void */
    private Closure $eliminarAjusteDevolucion;

    private ComisionCalculatorFactory $calculatorFactory;

    private ComisionReglaScope $reglaScope;

    /** @var Closure(int): \Illuminate\Support\Collection<int, object> */
    private Closure $obtenerReglasActivas;

    /**
     * @param  Closure(int, string): bool|null  $tieneFuncionalidad
     * @param  Closure(int): array<string, mixed>|null  $obtenerConfigComisiones
     * @param  Closure(array<string, mixed>, array<string, mixed>): object|null  $persistirMovimiento
     * @param  Closure(array<string, mixed>, array<string, mixed>): object|null  $persistirAjuste
     * @param  Closure(int): array<string, mixed>|null  $obtenerConfigGiftCards
     * @param  Closure(int): \Illuminate\Support\Collection<int, ComisionMovimiento>|null  $obtenerMovimientosVenta
     * @param  Closure(int): ?Venta|null  $obtenerVentaConDetalles
     * @param  Closure(int): \Illuminate\Support\Collection<int, Devolucion>|null  $obtenerDevolucionesActivas
     * @param  Closure(int, int): void|null  $eliminarAjusteDevolucion
     * @param  ComisionLiquidacionService|null  $liquidacionService
     * @param  Closure(int): \Illuminate\Support\Collection<int, object>|null  $obtenerReglasActivas
     */
    public function __construct(
        private ComisionPeriodoService $periodoService,
        private ComisionPorcentajeResolver $resolver,
        private ComisionBaseCalculator $calculator,
        ?Closure $tieneFuncionalidad = null,
        ?Closure $obtenerConfigComisiones = null,
        ?Closure $persistirMovimiento = null,
        ?Closure $persistirAjuste = null,
        ?Closure $obtenerConfigGiftCards = null,
        ?Closure $obtenerMovimientosVenta = null,
        ?Closure $obtenerVentaConDetalles = null,
        ?Closure $obtenerDevolucionesActivas = null,
        ?Closure $eliminarAjusteDevolucion = null,
        private ?ComisionLiquidacionService $liquidacionService = null,
        ?ComisionCalculatorFactory $calculatorFactory = null,
        ?ComisionReglaScope $reglaScope = null,
        ?Closure $obtenerReglasActivas = null
    ) {
        $this->liquidacionService ??= new ComisionLiquidacionService();
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
        $this->obtenerMovimientosVenta = $obtenerMovimientosVenta
            ?? fn (int $idVenta) => ComisionMovimiento::withoutGlobalScope('empresa')
                ->where('id_venta', $idVenta)
                ->where('origen', ComisionMovimiento::ORIGEN_VENTA)
                ->get();
        $this->obtenerVentaConDetalles = $obtenerVentaConDetalles
            ?? fn (int $idVenta) => Venta::with('detalles')->find($idVenta);
        $this->obtenerDevolucionesActivas = $obtenerDevolucionesActivas
            ?? fn (int $idVenta) => Devolucion::withoutGlobalScope('empresa')
                ->where('id_venta', $idVenta)
                ->where('enable', true)
                ->where('tipo', '!=', 'descuento_ajuste')
                ->with('detalles')
                ->get();
        $this->eliminarAjusteDevolucion = $eliminarAjusteDevolucion
            ?? function (int $idEmpresa, int $idMovimientoOrigen): void {
                ComisionMovimiento::withoutGlobalScope('empresa')
                    ->where('id_empresa', $idEmpresa)
                    ->where('origen', ComisionMovimiento::ORIGEN_AJUSTE_DEVOLUCION)
                    ->where('id_movimiento_origen', $idMovimientoOrigen)
                    ->delete();
            };
        $this->calculatorFactory = $calculatorFactory ?? new ComisionCalculatorFactory($this->resolver);
        $this->reglaScope = $reglaScope ?? new ComisionReglaScope();
        $this->obtenerReglasActivas = $obtenerReglasActivas
            ?? fn (int $idEmpresa) => ComisionRegla::withoutGlobalScope('empresa')
                ->where('id_empresa', $idEmpresa)
                ->where('activo', true)
                ->get();
    }

    public function registrarVentaPagada(object $venta): void
    {
        $this->registrarVentaPorMomento(
            $venta,
            ComisionRegla::MOMENTO_AL_PAGAR,
            ComisionMovimiento::ORIGEN_VENTA
        );
    }

    public function registrarVentaFacturada(object $venta): void
    {
        $this->registrarVentaPorMomento(
            $venta,
            ComisionRegla::MOMENTO_AL_FACTURAR,
            ComisionMovimiento::ORIGEN_VENTA
        );
    }

    public function registrarAbono(object $venta, object $abono): void
    {
        $this->registrarVentaPorMomento(
            $venta,
            ComisionRegla::MOMENTO_POR_ABONO,
            ComisionMovimiento::ORIGEN_ABONO,
            $abono
        );
    }

    private function registrarVentaPorMomento(
        object $venta,
        string $momento,
        string $origen,
        ?object $abono = null
    ): void {
        if (! ($this->tieneFuncionalidad)((int) $venta->id_empresa, self::SLUG_COMISIONES)) {
            return;
        }

        $config = ($this->obtenerConfigComisiones)((int) $venta->id_empresa);
        $baseCalculo = $config['base_calculo'] ?? 'subtotal_sin_iva';
        $fechaEvento = match ($momento) {
            ComisionRegla::MOMENTO_POR_ABONO => $abono->fecha ?? now(),
            ComisionRegla::MOMENTO_AL_FACTURAR => $venta->fecha ?? now(),
            default => $venta->fecha_pago ?? $venta->fecha ?? now(),
        };
        $periodo = $this->periodoService->periodoParaFecha((int) $venta->id_empresa, Carbon::parse($fechaEvento));
        // ponytail: prorrateo proporcional por total de venta; ceiling = no line-level payment allocation; upgrade = per-line gift application
        $fraccionGift = $momento === ComisionRegla::MOMENTO_AL_PAGAR
            ? $this->fraccionGiftCardEnVenta($venta)
            : 0.0;
        $fraccionAbono = 1.0;
        $idAbono = null;
        if ($abono !== null) {
            $idAbono = isset($abono->id) ? (int) $abono->id : null;
            $totalVenta = (float) ($venta->total ?? 0);
            $montoAbono = (float) ($abono->monto ?? $abono->total ?? 0);
            $fraccionAbono = $totalVenta > 0 ? $montoAbono / $totalVenta : 0.0;
            if ($fraccionAbono <= 0) {
                return;
            }
        }

        foreach ($venta->detalles as $detalle) {
            $this->registrarLineaVenta(
                (int) $venta->id_empresa,
                (int) $venta->id,
                $detalle,
                (int) ($venta->id_vendedor ?? 0),
                $baseCalculo,
                $periodo,
                $fechaEvento,
                $origen,
                null,
                $fraccionGift,
                $momento,
                $idAbono,
                $fraccionAbono
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

        $ajuste = ($this->persistirAjuste)($where, $values);

        if ($periodo->estado === ComisionPeriodo::ESTADO_CERRADO) {
            $this->liquidacionService->recalcularParaVendedorPeriodo(
                (int) $movimientoOriginal->id_empresa,
                (int) $periodo->id,
                (int) $movimientoOriginal->id_vendedor
            );
        }

        return $ajuste;
    }

    public function ajustarPorAnulacionVenta(int $idVenta, ?DateTimeInterface $fechaEvento = null): void
    {
        $movimientos = ($this->obtenerMovimientosVenta)($idVenta);

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
        $venta = ($this->obtenerVentaConDetalles)($idVenta);
        if ($venta === null) {
            return;
        }

        $config = ($this->obtenerConfigComisiones)((int) $devolucion->id_empresa);
        $baseCalculo = $config['base_calculo'] ?? 'subtotal_sin_iva';

        $devolucionesActivas = ($this->obtenerDevolucionesActivas)($idVenta);

        $movimientos = ($this->obtenerMovimientosVenta)($idVenta);

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
                ($this->eliminarAjusteDevolucion)((int) $movimiento->id_empresa, (int) $movimiento->id);

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
    ): ?object {
        if (! ($this->tieneFuncionalidad)($idEmpresa, self::SLUG_COMISIONES)) {
            return null;
        }

        if ($this->esCategoriaGiftCard($idEmpresa, $idCategoria)) {
            return null;
        }

        $config = ($this->obtenerConfigComisiones)($idEmpresa);
        $baseCalculo = $config['base_calculo'] ?? 'subtotal_sin_iva';
        $base = $this->calculator->calcular($detalleLinea, $baseCalculo);

        $resultados = $this->resultadosEnEvento(
            $idEmpresa,
            $idVendedor,
            $idCategoria,
            $idSubcategoria,
            $base,
            $detalleLinea,
            ComisionRegla::MOMENTO_AL_PAGAR
        );
        if ($resultados === []) {
            return null;
        }

        $periodo = $this->periodoService->periodoParaFecha($idEmpresa, Carbon::parse($fechaEvento));
        $ultimo = null;
        foreach ($resultados as [$regla, $resultado]) {
            $where = [
                'id_empresa' => $idEmpresa,
                'id_gift_card_redencion' => $idGiftCardRedencion,
            ];
            if ($regla !== null) {
                $where['id_regla'] = $regla->id;
            }

            $values = [
                'id_vendedor' => $idVendedor,
                'id_periodo' => $periodo->id,
                'origen' => ComisionMovimiento::ORIGEN_REDENCION_GIFT_CARD,
                'id_venta' => $idVenta,
                'id_detalle_venta' => $idDetalleVenta,
                'id_categoria' => $resultado->idCategoria,
                'id_subcategoria' => $resultado->idSubcategoria,
                'monto_base' => $resultado->montoBase,
                'porcentaje_aplicado' => $resultado->porcentaje,
                'monto_comision' => $resultado->montoComision,
                'fecha_evento' => $fechaEvento,
            ];

            $ultimo = ($this->persistirMovimiento)($where, $values);
        }

        return $ultimo;
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
        ?int $idGiftCardRedencion,
        float $fraccionGift = 0.0,
        string $momento = ComisionRegla::MOMENTO_AL_PAGAR,
        ?int $idAbono = null,
        float $fraccionAbono = 1.0
    ): ?object {
        $producto = $detalle->producto ?? null;
        $idCategoria = $producto ? (int) ($producto->id_categoria ?? 0) : null;
        $rawSub = $producto->id_subcategoria ?? $producto->subcategoria_id ?? null;
        $idSubcategoria = $producto && ! empty($rawSub) ? (int) $rawSub : null;

        if ($idCategoria === 0) {
            $idCategoria = null;
        }

        if ($this->esCategoriaGiftCard($idEmpresa, $idCategoria)) {
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
        if ($origen === ComisionMovimiento::ORIGEN_VENTA && $fraccionGift > 0) {
            $base = round($base * (1 - min(1.0, $fraccionGift)), 4);
        }
        if ($base <= 0) {
            return null;
        }

        $ultimo = null;
        foreach ($this->resultadosEnEvento(
            $idEmpresa,
            $idVendedor,
            $idCategoria,
            $idSubcategoria,
            $base,
            $detalle,
            $momento
        ) as [$regla, $resultado]) {
            $montoBase = $resultado->montoBase;
            $montoComision = $resultado->montoComision;
            if ($fraccionAbono !== 1.0) {
                $montoBase = round($montoBase * $fraccionAbono, 4);
                $montoComision = round($montoComision * $fraccionAbono, 4);
            }
            if ($montoComision == 0.0 && $montoBase == 0.0) {
                continue;
            }

            $where = [
                'id_empresa' => $idEmpresa,
                'origen' => $origen,
                'id_detalle_venta' => (int) $detalle->id,
            ];
            if ($regla !== null) {
                $where['id_regla'] = $regla->id;
            }
            if ($idAbono !== null) {
                $where['id_abono'] = $idAbono;
            }

            $values = [
                'id_vendedor' => $idVendedor,
                'id_periodo' => $periodo->id,
                'id_venta' => $idVenta,
                'id_gift_card_redencion' => $idGiftCardRedencion,
                'id_categoria' => $resultado->idCategoria,
                'id_subcategoria' => $resultado->idSubcategoria,
                'monto_base' => $montoBase,
                'porcentaje_aplicado' => $resultado->porcentaje,
                'monto_comision' => $montoComision,
                'fecha_evento' => $fechaEvento,
            ];

            $ultimo = ($this->persistirMovimiento)($where, $values);
        }

        return $ultimo;
    }

    /**
     * @return list<array{0: ?object, 1: ComisionCalculoResultado}>
     */
    private function resultadosEnEvento(
        int $idEmpresa,
        int $idVendedor,
        ?int $idCategoria,
        ?int $idSubcategoria,
        float $base,
        object $detalle,
        string $momento = ComisionRegla::MOMENTO_AL_PAGAR
    ): array {
        $reglas = ($this->obtenerReglasActivas)($idEmpresa);

        if ($reglas->isEmpty()) {
            if ($momento !== ComisionRegla::MOMENTO_AL_PAGAR) {
                return [];
            }
            $pct = $this->resolver->resolver($idEmpresa, $idCategoria, $idSubcategoria);
            if ($pct == 0.0) {
                return [];
            }

            return [[
                null,
                new ComisionCalculoResultado(
                    $base,
                    $pct,
                    round($base * ($pct / 100), 4),
                    $idCategoria,
                    $idSubcategoria,
                ),
            ]];
        }

        $aplicables = array_values(array_filter(
            $this->reglaScope->aplicables($reglas->all(), $idVendedor),
            fn (object $regla): bool => ($regla->momento_devengo ?? ComisionRegla::MOMENTO_AL_PAGAR) === $momento
        ));

        $out = [];
        foreach ($aplicables as $regla) {
            $resultado = $this->calculatorFactory
                ->for((string) $regla->tipo_calculo)
                ->calcularEnEvento((object) [
                    'id_empresa' => $idEmpresa,
                    'regla' => $regla,
                    'id_categoria' => $idCategoria,
                    'id_subcategoria' => $idSubcategoria,
                    'base' => $base,
                    'detalle' => $detalle,
                ]);
            if ($resultado === null) {
                continue;
            }
            $out[] = [$regla, $resultado];
        }

        return $out;
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

    private function fraccionGiftCardEnVenta(object $venta): float
    {
        $totalVenta = (float) ($venta->total ?? 0);
        if ($totalVenta <= 0) {
            return 0.0;
        }

        $montoGift = 0.0;
        $metodos = $venta->metodos_de_pago ?? null;
        if ($metodos !== null && count($metodos) > 0) {
            foreach ($metodos as $metodo) {
                if (Indicador::esFormaPagoGiftCard($metodo->nombre ?? null)) {
                    $montoGift += (float) ($metodo->total ?? 0);
                }
            }
        } elseif (Indicador::esFormaPagoGiftCard($venta->forma_pago ?? null)) {
            $montoGift = $totalVenta;
        }

        return min(1.0, $montoGift / $totalVenta);
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
