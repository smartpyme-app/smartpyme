<?php

namespace App\Services\Incentivos;

use App\Models\Bonos\BonoGenerado;
use App\Models\Bonos\BonoRegla;
use App\Models\Comisiones\ComisionMovimiento;
use App\Models\GiftCards\GiftCardRedencion;
use App\Models\User;
use App\Services\Bonos\BonoMetaCalculator;
use App\Services\Bonos\BonoReglaEvaluator;
use App\Services\Funcionalidades\FuncionalidadAccess;
use App\Services\Ventas\VentaMontosPorVendedorService;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VendedorConsolidadoService
{
    private const SLUG_COMISIONES = 'comisiones-vendedores';

    private const SLUG_BONOS = 'bonos-vendedores';

    private const SLUG_GIFT_CARDS = 'gift-cards';

    /** @var Closure(int, string): bool */
    private Closure $tieneFuncionalidad;

    /**
     * @param  Closure(int, string): bool|null  $tieneFuncionalidad
     */
    public function __construct(
        private BonoMetaCalculator $metaCalculator,
        private BonoReglaEvaluator $evaluator,
        ?Closure $tieneFuncionalidad = null,
    ) {
        $this->tieneFuncionalidad = $tieneFuncionalidad
            ?? fn (int $idEmpresa, string $slug) => FuncionalidadAccess::empresaTieneSlug($idEmpresa, $slug);
    }

    /**
     * @return array{periodo: array{inicio: string, fin: string}, data: array<int, array<string, mixed>>}
     */
    public function listar(int $idEmpresa, string $desde, string $hasta): array
    {
        $vendedorIds = $this->vendedorIdsEnPeriodo($idEmpresa, $desde, $hasta);
        $nombres = User::query()
            ->where('id_empresa', $idEmpresa)
            ->whereIn('id', $vendedorIds)
            ->pluck('name', 'id');

        $data = [];
        foreach ($vendedorIds as $idVendedor) {
            $detalle = $this->consolidado($idEmpresa, $idVendedor, $desde, $hasta);
            $data[] = [
                'id_vendedor' => $idVendedor,
                'nombre' => $nombres[$idVendedor] ?? 'Vendedor #' . $idVendedor,
                'total_a_pagar' => $detalle['total_a_pagar'],
            ];
        }

        usort($data, fn (array $a, array $b) => strcmp($a['nombre'], $b['nombre']));

        return [
            'periodo' => ['inicio' => $desde, 'fin' => $hasta],
            'data' => $data,
        ];
    }

    /** @return array<string, mixed> */
    public function consolidado(int $idEmpresa, int $idVendedor, string $desde, string $hasta): array
    {
        $flags = $this->flags($idEmpresa);
        $response = [
            'id_vendedor' => $idVendedor,
            'periodo' => ['inicio' => $desde, 'fin' => $hasta],
        ];

        $ventasPorCategoria = $this->ventasPorCategoria($idEmpresa, $idVendedor, $desde, $hasta);
        if (($flags['comisiones'] || $flags['bonos']) && $ventasPorCategoria !== []) {
            $response['ventas_por_categoria'] = $ventasPorCategoria;
        }

        $comisiones = $this->seccionComisiones($idEmpresa, $idVendedor, $desde, $hasta, $flags['comisiones']);
        if ($comisiones !== null) {
            $response['comisiones'] = $comisiones;
        }

        $bonos = $this->seccionBonos($idEmpresa, $idVendedor, $desde, $hasta, $flags['bonos']);
        if ($bonos !== null) {
            $response['bonos'] = $bonos;
        }

        $redenciones = $this->seccionRedencionesGift($idEmpresa, $idVendedor, $desde, $hasta, $flags['gift_cards']);
        if ($redenciones !== null) {
            $response['redenciones_gift'] = $redenciones;
        }

        $progreso = $this->seccionProgresoBono($idEmpresa, $idVendedor, $desde, $hasta, $flags['bonos']);
        if ($progreso !== null) {
            $response['progreso_bono'] = $progreso;
        }

        $totalComisiones = (float) ($comisiones['total'] ?? 0);
        $totalBonosPagables = $this->totalBonosAprobadosOPagados($idEmpresa, $idVendedor, $desde, $hasta);

        $response['total_a_pagar'] = [
            'comisiones' => round($totalComisiones, 2),
            'bonos_aprobados_o_pagados' => round($totalBonosPagables, 2),
            'desglose' => true,
        ];

        return $response;
    }

    /**
     * Bonos aprobados/pagados en el rango del comprobante (para PDF).
     *
     * @return array<int, array{id_regla: int, nombre: string, monto: float, estado: string}>
     */
    public function bonosComprobante(int $idEmpresa, int $idVendedor, string $desde, string $hasta): array
    {
        return $this->bonosEnPeriodo($idEmpresa, $idVendedor, $desde, $hasta);
    }

    /** @return array{comisiones: bool, bonos: bool, gift_cards: bool} */
    private function flags(int $idEmpresa): array
    {
        return [
            'comisiones' => ($this->tieneFuncionalidad)($idEmpresa, self::SLUG_COMISIONES),
            'bonos' => ($this->tieneFuncionalidad)($idEmpresa, self::SLUG_BONOS),
            'gift_cards' => ($this->tieneFuncionalidad)($idEmpresa, self::SLUG_GIFT_CARDS),
        ];
    }

    /** @return array<int> */
    private function vendedorIdsEnPeriodo(int $idEmpresa, string $desde, string $hasta): array
    {
        $exprVendedor = VentaMontosPorVendedorService::sqlIdVendedorEfectivo('dv', 'v');

        $desdeVentas = DB::table('detalles_venta as dv')
            ->join('ventas as v', 'v.id', '=', 'dv.id_venta')
            ->where('v.id_empresa', $idEmpresa)
            ->where('v.estado', 'Pagada')
            ->whereBetween('v.fecha', [$desde, $hasta])
            ->selectRaw("DISTINCT {$exprVendedor} as id_vendedor")
            ->pluck('id_vendedor');

        $desdeComisiones = ComisionMovimiento::withoutGlobalScope('empresa')
            ->where('id_empresa', $idEmpresa)
            ->where('id_vendedor', '>', 0)
            ->whereDate('fecha_evento', '>=', $desde)
            ->whereDate('fecha_evento', '<=', $hasta)
            ->distinct()
            ->pluck('id_vendedor');

        $desdeBonos = BonoGenerado::withoutGlobalScope('empresa')
            ->where('id_empresa', $idEmpresa)
            ->where('periodo_inicio', '<=', $hasta)
            ->where('periodo_fin', '>=', $desde)
            ->distinct()
            ->pluck('id_vendedor');

        $desdeGift = GiftCardRedencion::withoutGlobalScope('empresa')
            ->join('ventas as v', 'v.id', '=', 'gift_card_redenciones.id_venta')
            ->where('gift_card_redenciones.id_empresa', $idEmpresa)
            ->whereBetween('v.fecha', [$desde, $hasta])
            ->when(
                Schema::hasColumn('gift_card_redenciones', 'reversed_at'),
                fn ($q) => $q->whereNull('gift_card_redenciones.reversed_at')
            )
            ->distinct()
            ->pluck('gift_card_redenciones.id_vendedor');

        return $desdeVentas
            ->merge($desdeComisiones)
            ->merge($desdeBonos)
            ->merge($desdeGift)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int, array{id_categoria: int, nombre: string, monto: float}> */
    private function ventasPorCategoria(int $idEmpresa, int $idVendedor, string $desde, string $hasta): array
    {
        $exprVendedor = VentaMontosPorVendedorService::sqlIdVendedorEfectivo('dv', 'v');

        $rows = DB::table('detalles_venta as dv')
            ->join('ventas as v', 'v.id', '=', 'dv.id_venta')
            ->join('productos as p', 'p.id', '=', 'dv.id_producto')
            ->join('categorias as c', 'c.id', '=', 'p.id_categoria')
            ->where('v.id_empresa', $idEmpresa)
            ->where('v.estado', 'Pagada')
            ->whereBetween('v.fecha', [$desde, $hasta])
            ->whereRaw("{$exprVendedor} = ?", [$idVendedor])
            ->groupBy('p.id_categoria', 'c.nombre')
            ->selectRaw('p.id_categoria as id_categoria, c.nombre as nombre, SUM(COALESCE(dv.total, 0) + COALESCE(dv.iva, 0)) as monto')
            ->orderBy('c.nombre')
            ->get();

        return $rows->map(fn ($row) => [
            'id_categoria' => (int) $row->id_categoria,
            'nombre' => (string) $row->nombre,
            'monto' => round((float) $row->monto, 2),
        ])->values()->all();
    }

    /** @return array{por_categoria: array<int, array<string, mixed>>, por_redencion_gift: float, total: float}|null */
    private function seccionComisiones(int $idEmpresa, int $idVendedor, string $desde, string $hasta, bool $flagOn): ?array
    {
        $movimientos = ComisionMovimiento::withoutGlobalScope('empresa')
            ->where('id_empresa', $idEmpresa)
            ->where('id_vendedor', $idVendedor)
            ->whereDate('fecha_evento', '>=', $desde)
            ->whereDate('fecha_evento', '<=', $hasta)
            ->with('categoria')
            ->get();

        if (!$flagOn && $movimientos->isEmpty()) {
            return null;
        }

        $porCategoria = [];
        $porRedencionGift = 0.0;

        foreach ($movimientos as $mov) {
            $monto = (float) $mov->monto_comision;
            if ($mov->origen === ComisionMovimiento::ORIGEN_REDENCION_GIFT_CARD) {
                $porRedencionGift += $monto;
                continue;
            }

            $idCat = (int) ($mov->id_categoria ?? 0);
            if (!isset($porCategoria[$idCat])) {
                $porCategoria[$idCat] = [
                    'id_categoria' => $idCat,
                    'nombre' => $mov->categoria?->nombre ?? 'Sin categoría',
                    'monto' => 0.0,
                ];
            }
            $porCategoria[$idCat]['monto'] += $monto;
        }

        $porCategoriaList = array_values(array_map(function (array $row) {
            $row['monto'] = round($row['monto'], 2);

            return $row;
        }, $porCategoria));

        usort($porCategoriaList, fn (array $a, array $b) => strcmp($a['nombre'], $b['nombre']));

        return [
            'por_categoria' => $porCategoriaList,
            'por_redencion_gift' => round($porRedencionGift, 2),
            'total' => round((float) $movimientos->sum('monto_comision'), 2),
        ];
    }

    /** @return array<int, array{id_regla: int, nombre: string, monto: float, estado: string}>|null */
    private function seccionBonos(int $idEmpresa, int $idVendedor, string $desde, string $hasta, bool $flagOn): ?array
    {
        $bonos = $this->bonosEnPeriodo($idEmpresa, $idVendedor, $desde, $hasta);

        if (!$flagOn && $bonos === []) {
            return null;
        }

        return $bonos;
    }

    /** @return array<int, array{id_regla: int, nombre: string, monto: float, estado: string}> */
    private function bonosEnPeriodo(int $idEmpresa, int $idVendedor, string $desde, string $hasta): array
    {
        return BonoGenerado::withoutGlobalScope('empresa')
            ->where('id_empresa', $idEmpresa)
            ->where('id_vendedor', $idVendedor)
            ->where('periodo_inicio', '<=', $hasta)
            ->where('periodo_fin', '>=', $desde)
            ->with('regla')
            ->orderBy('periodo_inicio')
            ->orderBy('id')
            ->get()
            ->map(fn (BonoGenerado $bono) => [
                'id_regla' => (int) $bono->id_regla,
                'nombre' => $bono->regla?->nombre ?? 'Regla #' . $bono->id_regla,
                'monto' => round((float) $bono->monto, 2),
                'estado' => $bono->estado,
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array{codigo: string, monto: float}>|null */
    private function seccionRedencionesGift(int $idEmpresa, int $idVendedor, string $desde, string $hasta, bool $flagOn): ?array
    {
        $query = GiftCardRedencion::withoutGlobalScope('empresa')
            ->where('gift_card_redenciones.id_empresa', $idEmpresa)
            ->where('gift_card_redenciones.id_vendedor', $idVendedor)
            ->join('ventas as v', 'v.id', '=', 'gift_card_redenciones.id_venta')
            ->whereBetween('v.fecha', [$desde, $hasta]);

        if (Schema::hasColumn('gift_card_redenciones', 'reversed_at')) {
            $query->whereNull('gift_card_redenciones.reversed_at');
        }

        $rows = $query
            ->with('giftCard:id,codigo')
            ->select('gift_card_redenciones.*')
            ->orderBy('gift_card_redenciones.id')
            ->get();

        if (!$flagOn && $rows->isEmpty()) {
            return null;
        }

        return $rows->map(fn (GiftCardRedencion $r) => [
            'codigo' => $r->giftCard?->codigo ?? '—',
            'monto' => round((float) $r->monto, 2),
        ])->values()->all();
    }

    /** @return array<int, array{regla: string, actual: float, meta: float, faltante: float}>|null */
    private function seccionProgresoBono(int $idEmpresa, int $idVendedor, string $desde, string $hasta, bool $flagOn): ?array
    {
        if (!$flagOn) {
            return null;
        }

        $reglas = BonoRegla::withoutGlobalScope('empresa')
            ->where('id_empresa', $idEmpresa)
            ->where('activo', true)
            ->where('tipo', BonoRegla::TIPO_META_FIJA)
            ->get();

        if ($reglas->isEmpty()) {
            return null;
        }

        $ventas = $this->metaCalculator->ventasVendedorPeriodo($idEmpresa, $idVendedor, $desde, $hasta);

        return $reglas->map(function (BonoRegla $regla) use ($ventas) {
            $meta = (float) ($regla->config['meta'] ?? 0);
            $faltante = max(0.0, $meta - $ventas);

            return [
                'regla' => $regla->nombre,
                'actual' => round($ventas, 2),
                'meta' => round($meta, 2),
                'faltante' => round($faltante, 2),
            ];
        })->values()->all();
    }

    private function totalBonosAprobadosOPagados(int $idEmpresa, int $idVendedor, string $desde, string $hasta): float
    {
        return (float) BonoGenerado::withoutGlobalScope('empresa')
            ->where('id_empresa', $idEmpresa)
            ->where('id_vendedor', $idVendedor)
            ->where('periodo_inicio', '<=', $hasta)
            ->where('periodo_fin', '>=', $desde)
            ->whereIn('estado', [BonoGenerado::ESTADO_APROBADO, BonoGenerado::ESTADO_PAGADO])
            ->sum('monto');
    }
}
