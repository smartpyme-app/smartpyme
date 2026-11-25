<?php

namespace App\Services\Inventario;

use App\Constants\ConsignaMovimientoTipoConstants;
use App\Models\Admin\Empresa;
use App\Models\Inventario\ConsignaCustodia;
use App\Models\Inventario\ConsignaMovimiento;
use App\Models\Inventario\Lote;
use App\Models\Ventas\Detalle as DetalleVenta;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ConsignaHistorialService
{
    public function __construct(
        private ConsignaMovimientoService $movimientoService
    ) {}

    public function agruparPorLote(?Empresa $empresa): bool
    {
        return $empresa?->isModuloConsignacionesLedgerActivo()
            && $empresa?->isLotesActivo();
    }

    /**
     * @return array{resumen: list<array<string, mixed>>, movimientos: list<array<string, mixed>>, incluir_lote: bool, tercero: ?array<string, mixed>}
     */
    public function reporte(Request $request, int $idEmpresa, Empresa $empresa): array
    {
        $alcance = $this->alcanceDesdeRequest($request);
        $incluirLote = $this->agruparPorLote($empresa);
        $movimientos = $this->coleccionMovimientos($request, $idEmpresa, $alcance, true);
        $movimientosSaldo = $this->coleccionMovimientos($request, $idEmpresa, $alcance, false);

        $resumen = $alcance === 'ventas' && $request->filled('id_cliente')
            ? $this->resumenVentasCliente($request, $idEmpresa, $empresa, $movimientosSaldo, $incluirLote)
            : $this->resumenComprasProveedor($movimientosSaldo, $incluirLote);

        $terceroFuente = $movimientosSaldo->isNotEmpty() ? $movimientosSaldo : $movimientos;

        return [
            'alcance' => $alcance,
            'incluir_lote' => $incluirLote,
            'tercero' => $this->terceroDesdeMovimientos($terceroFuente, $alcance),
            'resumen' => $resumen,
            'movimientos' => $movimientos
                ->sortByDesc(fn (ConsignaMovimiento $m) => $m->fecha?->format('Y-m-d H:i:s') . ':' . $m->id)
                ->values()
                ->map(fn (ConsignaMovimiento $m) => $this->filaHistorial($m, $incluirLote))
                ->all(),
        ];
    }

    public function alcanceDesdeRequest(Request $request): string
    {
        $alcance = strtolower((string) $request->input('alcance', 'ventas'));

        return $alcance === 'compras' ? 'compras' : 'ventas';
    }

    /** @return Collection<int, ConsignaMovimiento> */
    public function coleccionMovimientos(
        Request $request,
        int $idEmpresa,
        string $alcance,
        bool $aplicarRangoFecha = true
    ): Collection {
        $tipos = $alcance === 'ventas'
            ? ConsignaMovimientoTipoConstants::tiposKardexCustodiaCliente()
            : ConsignaMovimientoTipoConstants::tiposKardexPoolProveedor();

        $query = ConsignaMovimiento::query()
            ->forEmpresa($idEmpresa)
            ->with([
                'producto:id,nombre,codigo',
                'bodega:id,nombre',
                'lote:id,numero_lote,fecha_vencimiento',
                'detalleCompra.lote:id,numero_lote,fecha_vencimiento',
                'detalleVenta.lote:id,numero_lote,fecha_vencimiento',
                'venta.documento:id,nombre',
                'compra:id,tipo_documento,referencia',
                'cliente' => fn ($q) => $q->withoutGlobalScopes()->select(
                    'id', 'nombre', 'apellido', 'nombre_comercial', 'nombre_empresa'
                ),
                'proveedor' => fn ($q) => $q->withoutGlobalScopes()->select(
                    'id', 'nombre', 'apellido', 'tipo', 'nombre_empresa', 'nombre_comercial'
                ),
            ])
            ->whereIn('tipo', $tipos)
            ->orderBy('fecha')
            ->orderBy('id');

        if ($aplicarRangoFecha && $request->filled('fecha_ini') && $request->filled('fecha_fin')) {
            $query->whereBetween('fecha', [$request->fecha_ini, $request->fecha_fin]);
        }

        if ($alcance === 'ventas') {
            if ($request->filled('id_cliente')) {
                $query->where('id_cliente', (int) $request->id_cliente);
            }
        } elseif ($request->filled('id_proveedor')) {
            $query->where('id_proveedor', (int) $request->id_proveedor);
        }

        return $query->limit(15000)->get();
    }

    /**
     * Saldo actual: solo filas con disponible > 0 (lo que aún está en consigna).
     *
     * @param  list<array<string, mixed>>  $resumen
     * @return list<array<string, mixed>>
     */
    protected function listadoVentasConsigna(): ConsignaProductosVentaListadoService
    {
        return app(ConsignaProductosVentaListadoService::class);
    }

    /**
     * @return Collection<int, ConsignaCustodia>
     */
    protected function custodiasActivasCliente(int $idEmpresa, int $idCliente): Collection
    {
        return ConsignaCustodia::query()
            ->forEmpresa($idEmpresa)
            ->where('id_cliente', $idCliente)
            ->where('cantidad', '>', 0.0001)
            ->with(['producto:id,nombre,codigo', 'bodega:id,nombre'])
            ->orderBy('id_producto')
            ->get();
    }

    private function filtrarResumenProductosEnConsigna(array $resumen): array
    {
        return array_values(array_filter(
            $resumen,
            static fn (array $fila) => (float) ($fila['disponible'] ?? 0) > 0.0001
        ));
    }

    /**
     * Saldo vigente en consigna_custodias (fuente operativa); totales históricos desde ledger agregado por bodega.
     *
     * @return list<array<string, mixed>>
     */
    private function resumenVentasCliente(
        Request $request,
        int $idEmpresa,
        Empresa $empresa,
        Collection $movimientosSaldo,
        bool $mostrarColumnasLote
    ): array {
        $idCliente = (int) $request->id_cliente;

        $desdeRemisiones = $this->listadoVentasConsigna()->resumenFilasCliente($idCliente, $empresa);
        if ($desdeRemisiones !== []) {
            return $this->mezclarResumenConStatsLedger($desdeRemisiones, $movimientosSaldo, $mostrarColumnasLote);
        }

        $statsPorClave = $this->indexarResumenPorProductoBodega(
            $this->resumenDesdeMovimientos($movimientosSaldo, 'ventas', false)
        );

        $custodias = $this->custodiasActivasCliente($idEmpresa, $idCliente);

        $filas = [];
        foreach ($custodias as $c) {
            $key = (int) $c->id_producto . ':' . (int) $c->id_bodega;
            $st = $statsPorClave[$key] ?? [];
            $lote = $mostrarColumnasLote
                ? $this->loteDestacadoEnMovimientos($movimientosSaldo, (int) $c->id_producto, (int) $c->id_bodega)
                : null;

            $filas[] = [
                'id_producto' => (int) $c->id_producto,
                'id_bodega' => (int) $c->id_bodega,
                'codigo' => $c->producto?->codigo,
                'producto' => $c->producto?->nombre,
                'lote_id' => $lote['lote_id'] ?? null,
                'lote' => $lote['lote'] ?? null,
                'vence' => $lote['vence'] ?? null,
                'bodega' => $c->bodega?->nombre,
                'enviadas' => (float) ($st['enviadas'] ?? 0),
                'facturadas' => (float) ($st['facturadas'] ?? 0),
                'devueltas' => (float) ($st['devueltas'] ?? 0),
                'disponible' => round((float) $c->cantidad, 4),
                'precio_unitario' => (float) ($st['precio_unitario'] ?? 0),
            ];
        }

        if ($filas !== []) {
            return $filas;
        }

        $desdeLedger = $this->resumenLedgerConFallback($movimientosSaldo, 'ventas', $mostrarColumnasLote);
        if ($desdeLedger !== []) {
            return $desdeLedger;
        }

        return $this->resumenVentasDesdeRemisionesAbiertas($idEmpresa, $idCliente, $mostrarColumnasLote);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function mezclarResumenConStatsLedger(
        array $filas,
        Collection $movimientosSaldo,
        bool $mostrarColumnasLote
    ): array {
        $statsPorProducto = $this->indexarResumenPorProducto($movimientosSaldo);

        return array_map(function (array $fila) use ($statsPorProducto, $movimientosSaldo, $mostrarColumnasLote) {
            $idProducto = (int) ($fila['id_producto'] ?? 0);
            $st = $statsPorProducto[$idProducto] ?? null;
            if ($st) {
                $fila['enviadas'] = (float) ($st['enviadas'] ?? $fila['enviadas']);
                $fila['facturadas'] = (float) ($st['facturadas'] ?? 0);
                $fila['devueltas'] = (float) ($st['devueltas'] ?? 0);
                if (! empty($st['bodega'])) {
                    $fila['bodega'] = $st['bodega'];
                }
                if ((float) ($st['precio_unitario'] ?? 0) > 0) {
                    $fila['precio_unitario'] = (float) $st['precio_unitario'];
                }
            }
            if ($mostrarColumnasLote && empty($fila['lote']) && $idProducto > 0) {
                $lote = $this->loteDestacadoEnMovimientos($movimientosSaldo, $idProducto, (int) ($fila['id_bodega'] ?? 0));
                if ($lote) {
                    $fila['lote_id'] = $lote['lote_id'];
                    $fila['lote'] = $lote['lote'];
                    $fila['vence'] = $lote['vence'];
                }
            }

            return $fila;
        }, $filas);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function indexarResumenPorProducto(Collection $movimientosSaldo): array
    {
        $porBodega = $this->resumenDesdeMovimientos($movimientosSaldo, 'ventas', false);
        $out = [];
        foreach ($porBodega as $fila) {
            $idProducto = (int) ($fila['id_producto'] ?? 0);
            if (! isset($out[$idProducto])) {
                $out[$idProducto] = $fila;

                continue;
            }
            $out[$idProducto]['enviadas'] = (float) $out[$idProducto]['enviadas'] + (float) ($fila['enviadas'] ?? 0);
            $out[$idProducto]['facturadas'] = (float) $out[$idProducto]['facturadas'] + (float) ($fila['facturadas'] ?? 0);
            $out[$idProducto]['devueltas'] = (float) $out[$idProducto]['devueltas'] + (float) ($fila['devueltas'] ?? 0);
        }

        return $out;
    }

    /**
     * Remisiones / ventas con estado Consigna aún sin liquidar (alineado con listado productos/consignas).
     *
     * @return list<array<string, mixed>>
     */
    private function resumenVentasDesdeRemisionesAbiertas(
        int $idEmpresa,
        int $idCliente,
        bool $mostrarColumnasLote
    ): array {
        $detalles = DetalleVenta::query()
            ->whereHas('venta', fn ($q) => $q->where('estado', 'Consigna')->where('cotizacion', 0))
            ->with([
                'producto:id,nombre,codigo',
                'lote:id,numero_lote,fecha_vencimiento',
                'venta:id,id_bodega',
                'venta.bodega:id,nombre',
            ])
            ->get()
            ->filter(fn (DetalleVenta $detalle) => (int) ($detalle->venta?->id_cliente ?? 0) === $idCliente);

        if ($detalles->isEmpty()) {
            return [];
        }

        /** @var array<string, array<string, mixed>> $filas */
        $filas = [];

        foreach ($detalles as $detalle) {
            $idProducto = (int) $detalle->id_producto;
            $idBodega = (int) ($detalle->venta?->id_bodega ?? 0);
            $loteId = $detalle->lote_id ? (int) $detalle->lote_id : 0;
            $key = $mostrarColumnasLote
                ? $idProducto . ':' . $idBodega . ':' . $loteId
                : $idProducto . ':' . $idBodega;

            if (! isset($filas[$key])) {
                $filas[$key] = [
                    'id_producto' => $idProducto,
                    'id_bodega' => $idBodega,
                    'codigo' => $detalle->producto?->codigo,
                    'producto' => $detalle->producto?->nombre,
                    'lote_id' => $detalle->lote_id ? (int) $detalle->lote_id : null,
                    'lote' => $detalle->lote?->numero_lote,
                    'vence' => $detalle->lote?->fecha_vencimiento?->format('Y-m-d'),
                    'bodega' => $detalle->venta?->bodega?->nombre,
                    'enviadas' => 0.0,
                    'facturadas' => 0.0,
                    'devueltas' => 0.0,
                    'disponible' => 0.0,
                    'precio_unitario' => (float) ($detalle->precio ?? 0),
                ];
            }

            $qty = (float) $detalle->cantidad;
            $filas[$key]['enviadas'] = (float) $filas[$key]['enviadas'] + $qty;
            $filas[$key]['disponible'] = (float) $filas[$key]['disponible'] + $qty;
            if ($detalle->precio) {
                $filas[$key]['precio_unitario'] = (float) $detalle->precio;
            }
        }

        foreach ($filas as &$fila) {
            $fila['enviadas'] = round((float) $fila['enviadas'], 4);
            $fila['disponible'] = round((float) $fila['disponible'], 4);
        }
        unset($fila);

        return collect($filas)
            ->sortBy(fn ($r) => ($r['producto'] ?? '') . ($r['lote'] ?? ''))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resumenComprasProveedor(Collection $movimientosSaldo, bool $mostrarColumnasLote): array
    {
        return $this->resumenLedgerConFallback($movimientosSaldo, 'compras', $mostrarColumnasLote);
    }

    /**
     * Agregado por producto+bodega (sin lote) para no partir el saldo cuando hay lotes activos.
     *
     * @return list<array<string, mixed>>
     */
    private function resumenLedgerConFallback(
        Collection $movimientosSaldo,
        string $alcance,
        bool $mostrarColumnasLote
    ): array {
        $porBodega = $this->filtrarResumenProductosEnConsigna(
            $this->resumenDesdeMovimientos($movimientosSaldo, $alcance, false)
        );

        if ($porBodega !== []) {
            return $this->enriquecerFilasResumenConLote($porBodega, $movimientosSaldo, $mostrarColumnasLote);
        }

        if ($mostrarColumnasLote) {
            $porLote = $this->filtrarResumenProductosEnConsigna(
                $this->resumenDesdeMovimientos($movimientosSaldo, $alcance, true)
            );
            if ($porLote !== []) {
                return $porLote;
            }
        }

        return [];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function enriquecerFilasResumenConLote(
        array $filas,
        Collection $movimientosSaldo,
        bool $mostrarColumnasLote
    ): array {
        if (! $mostrarColumnasLote) {
            return $filas;
        }

        return array_map(function (array $fila) use ($movimientosSaldo) {
            $lote = $this->loteDestacadoEnMovimientos(
                $movimientosSaldo,
                (int) ($fila['id_producto'] ?? 0),
                (int) ($fila['id_bodega'] ?? 0)
            );
            if ($lote) {
                $fila['lote_id'] = $lote['lote_id'];
                $fila['lote'] = $lote['lote'];
                $fila['vence'] = $lote['vence'];
            }

            return $fila;
        }, $filas);
    }

    /**
     * @param  list<array<string, mixed>>  $resumen
     * @return array<string, array<string, mixed>>
     */
    private function indexarResumenPorProductoBodega(array $resumen): array
    {
        $out = [];
        foreach ($resumen as $fila) {
            $productoId = (int) ($fila['id_producto'] ?? 0);
            $bodegaId = (int) ($fila['id_bodega'] ?? 0);
            $key = $productoId . ':' . $bodegaId;
            if (! isset($out[$key])) {
                $out[$key] = $fila;

                continue;
            }
            $out[$key]['enviadas'] = (float) $out[$key]['enviadas'] + (float) ($fila['enviadas'] ?? 0);
            $out[$key]['facturadas'] = (float) $out[$key]['facturadas'] + (float) ($fila['facturadas'] ?? 0);
            $out[$key]['devueltas'] = (float) $out[$key]['devueltas'] + (float) ($fila['devueltas'] ?? 0);
            $out[$key]['disponible'] = (float) $out[$key]['disponible'] + (float) ($fila['disponible'] ?? 0);
        }

        return $out;
    }

    /**
     * @return array{lote_id: int, lote: string, vence: ?string}|null
     */
    private function loteDestacadoEnMovimientos(
        Collection $movimientos,
        int $idProducto,
        int $idBodega
    ): ?array {
        $candidato = null;
        foreach ($movimientos as $m) {
            if ((int) $m->id_producto !== $idProducto || (int) $m->id_bodega !== $idBodega) {
                continue;
            }
            $lote = $this->loteMovimiento($m);
            if (! $lote) {
                continue;
            }
            if ($candidato === null || abs((float) $m->cantidad) > $candidato['qty']) {
                $candidato = [
                    'lote_id' => (int) $lote->id,
                    'lote' => (string) $lote->numero_lote,
                    'vence' => $lote->fecha_vencimiento?->format('Y-m-d'),
                    'qty' => abs((float) $m->cantidad),
                ];
            }
        }
        if ($candidato === null) {
            return null;
        }
        unset($candidato['qty']);

        return $candidato;
    }

    /**
     * @param  Collection<int, ConsignaMovimiento>  $movimientos
     * @return list<array<string, mixed>>
     */
    private function resumenDesdeMovimientos(Collection $movimientos, string $alcance, bool $incluirLote): array
    {
        /** @var array<string, array<string, mixed>> $filas */
        $filas = [];

        foreach ($movimientos as $m) {
            $key = $this->claveResumen($m, $incluirLote);
            if (! isset($filas[$key])) {
                $lote = $this->loteMovimiento($m);
                $filas[$key] = [
                    'id_producto' => (int) $m->id_producto,
                    'id_bodega' => (int) $m->id_bodega,
                    'codigo' => $m->producto?->codigo,
                    'producto' => $m->producto?->nombre,
                    'lote_id' => $lote?->id,
                    'lote' => $lote?->numero_lote,
                    'vence' => $lote?->fecha_vencimiento?->format('Y-m-d'),
                    'bodega' => $m->bodega?->nombre,
                    'enviadas' => 0.0,
                    'facturadas' => 0.0,
                    'devueltas' => 0.0,
                    'precio_unitario' => (float) ($m->costo_unitario ?? 0),
                ];
            }

            $qty = abs((float) $m->cantidad);

            if ($alcance === 'ventas') {
                match ($m->tipo) {
                    ConsignaMovimientoTipoConstants::ENTREGA_CONSIGNA_PROPIO,
                    ConsignaMovimientoTipoConstants::ENTREGA_CONSIGNA_PROVEEDOR => $filas[$key]['enviadas'] += $qty,
                    ConsignaMovimientoTipoConstants::LIQUIDACION_CLIENTE => $filas[$key]['facturadas'] += $qty,
                    ConsignaMovimientoTipoConstants::DEVOLUCION_CLIENTE => $filas[$key]['devueltas'] += $qty,
                    default => null,
                };
            } else {
                match ($m->tipo) {
                    ConsignaMovimientoTipoConstants::ENTRADA_PROVEEDOR => $filas[$key]['enviadas'] += $qty,
                    ConsignaMovimientoTipoConstants::VENTA_REGULAR_CONSIGNA_PROVEEDOR => $filas[$key]['facturadas'] += $qty,
                    ConsignaMovimientoTipoConstants::DEVOLUCION_PROVEEDOR => $filas[$key]['devueltas'] += $qty,
                    default => null,
                };
            }

            if ($m->costo_unitario) {
                $filas[$key]['precio_unitario'] = (float) $m->costo_unitario;
            }
        }

        foreach ($filas as &$fila) {
            $fila['disponible'] = max(
                0,
                round((float) $fila['enviadas'] - (float) $fila['facturadas'] - (float) $fila['devueltas'], 4)
            );
        }
        unset($fila);

        return collect($filas)
            ->sortBy(fn ($r) => ($r['producto'] ?? '') . ($r['lote'] ?? ''))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function filaHistorial(ConsignaMovimiento $m, bool $incluirLote): array
    {
        $enriquecido = $this->movimientoService->enriquecerMovimientoParaApi($m);
        $lote = $this->loteMovimiento($m);

        return [
            'fecha' => $m->fecha?->format('Y-m-d'),
            'tipo' => $this->codigoTipoHistorial($m),
            'tipo_etiqueta' => $enriquecido['tipo_etiqueta'] ?? $m->tipo,
            'codigo' => $m->producto?->codigo,
            'producto' => $m->producto?->nombre,
            'cantidad' => abs((float) $m->cantidad),
            'notas' => $this->notaHistorial($m, $enriquecido),
            'documento' => $enriquecido['documento'] ?? null,
            'lote' => $incluirLote ? $lote?->numero_lote : null,
            'vence' => $incluirLote ? $lote?->fecha_vencimiento?->format('Y-m-d') : null,
        ];
    }

    private function codigoTipoHistorial(ConsignaMovimiento $m): string
    {
        if ($m->tipo === ConsignaMovimientoTipoConstants::DEVOLUCION_CLIENTE
            || $m->tipo === ConsignaMovimientoTipoConstants::DEVOLUCION_PROVEEDOR) {
            return 'DEV';
        }

        if ($m->tipo === ConsignaMovimientoTipoConstants::LIQUIDACION_CLIENTE) {
            $doc = strtolower((string) ($m->venta?->documento?->nombre ?? ''));
            if (str_contains($doc, 'crédito') || str_contains($doc, 'credito')) {
                return 'CCF';
            }
            if (str_contains($doc, 'ticket')) {
                return 'TKT';
            }

            return 'FAC';
        }

        return match ($m->tipo) {
            ConsignaMovimientoTipoConstants::ENTREGA_CONSIGNA_PROPIO,
            ConsignaMovimientoTipoConstants::ENTREGA_CONSIGNA_PROVEEDOR => 'REM',
            ConsignaMovimientoTipoConstants::ENTRADA_PROVEEDOR => 'ENT',
            ConsignaMovimientoTipoConstants::VENTA_REGULAR_CONSIGNA_PROVEEDOR => 'VTA',
            ConsignaMovimientoTipoConstants::LIQUIDACION_PROVEEDOR => 'LIQ',
            default => strtoupper(substr((string) $m->tipo, 0, 7)),
        };
    }

    /** @param  array<string, mixed>  $enriquecido */
    private function notaHistorial(ConsignaMovimiento $m, array $enriquecido): string
    {
        $partes = array_filter([
            $enriquecido['documento'] ?? null,
            $m->nota,
        ]);

        return $partes === [] ? ($enriquecido['tipo_etiqueta'] ?? $m->tipo) : implode(' — ', $partes);
    }

    private function claveResumen(ConsignaMovimiento $m, bool $incluirLote): string
    {
        $loteId = $this->loteMovimiento($m)?->id ?? 0;

        return $incluirLote
            ? $m->id_producto . ':' . $m->id_bodega . ':' . $loteId
            : $m->id_producto . ':' . $m->id_bodega;
    }

    private function loteMovimiento(ConsignaMovimiento $m): ?Lote
    {
        if ($m->relationLoaded('lote') && $m->lote) {
            return $m->lote;
        }
        if ($m->detalleVenta?->lote) {
            return $m->detalleVenta->lote;
        }
        if ($m->detalleCompra?->lote) {
            return $m->detalleCompra->lote;
        }

        return $m->lote;
    }

    /**
     * @param  Collection<int, ConsignaMovimiento>  $movimientos
     * @return array<string, mixed>|null
     */
    private function terceroDesdeMovimientos(Collection $movimientos, string $alcance): ?array
    {
        $primero = $movimientos->first();
        if (! $primero) {
            return null;
        }

        if ($alcance === 'ventas' && $primero->cliente) {
            $c = $primero->cliente;

            return [
                'id' => $c->id,
                'nombre' => trim($c->nombre_comercial ?: $c->nombre_empresa ?: ($c->nombre . ' ' . $c->apellido)),
            ];
        }

        if ($alcance === 'compras' && $primero->proveedor) {
            $p = $primero->proveedor;

            return [
                'id' => $p->id,
                'nombre' => trim($p->nombre_comercial ?: $p->nombre_empresa ?: ($p->nombre . ' ' . $p->apellido)),
            ];
        }

        return null;
    }
}
