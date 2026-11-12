<?php

namespace App\Services\Inventario;

use App\Constants\ConsignaMovimientoTipoConstants;
use App\Models\Admin\Empresa;
use App\Models\Inventario\ConsignaMovimiento;
use App\Models\Inventario\Lote;
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
        $movimientos = $this->coleccionMovimientos($request, $idEmpresa, $alcance);

        return [
            'alcance' => $alcance,
            'incluir_lote' => $incluirLote,
            'tercero' => $this->terceroDesdeMovimientos($movimientos, $alcance),
            'resumen' => $this->resumenDesdeMovimientos($movimientos, $alcance, $incluirLote),
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
    public function coleccionMovimientos(Request $request, int $idEmpresa, string $alcance): Collection
    {
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

        if ($request->filled('fecha_ini') && $request->filled('fecha_fin')) {
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
