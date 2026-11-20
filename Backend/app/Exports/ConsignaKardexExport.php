<?php

namespace App\Exports;

use App\Constants\ConsignaMovimientoTipoConstants;
use App\Models\Inventario\ConsignaMovimiento;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ConsignaKardexExport implements FromCollection, WithHeadings, WithMapping
{
    private Request $request;

    private int $idEmpresa;

    public function __construct(Request $request, int $idEmpresa)
    {
        $this->request = $request;
        $this->idEmpresa = $idEmpresa;
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Tipo',
            'Tipo (etiqueta)',
            'Documento',
            'Producto',
            'Bodega',
            'Lote',
            'Cantidad',
            'Costo unitario',
            'Proveedor ID',
            'Cliente ID',
            'Compra ID',
            'Venta ID',
            'Nota',
        ];
    }

    public function map($row): array
    {
        return [
            $row->fecha,
            $row->tipo,
            ConsignaMovimientoTipoConstants::etiqueta($row->tipo),
            $this->documento($row),
            $row->producto?->nombre ?? $row->id_producto,
            $row->bodega?->nombre ?? $row->id_bodega,
            $row->lote?->numero_lote,
            $row->cantidad,
            $row->costo_unitario,
            $row->id_proveedor,
            $row->id_cliente,
            $row->id_compra,
            $row->id_venta,
            $row->nota,
        ];
    }

    public function collection()
    {
        $query = ConsignaMovimiento::query()
            ->forEmpresa($this->idEmpresa)
            ->with([
                'producto:id,nombre',
                'bodega:id,nombre',
                'lote:id,numero_lote',
                'compra:id,tipo_documento,referencia,id_empresa',
                'venta.documento:id,nombre',
            ])
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if ($this->request->filled('fecha_ini') && $this->request->filled('fecha_fin')) {
            $query->whereBetween('fecha', [$this->request->fecha_ini, $this->request->fecha_fin]);
        }
        if ($this->request->filled('id_producto')) {
            $query->where('id_producto', (int) $this->request->id_producto);
        }
        if ($this->request->filled('id_bodega')) {
            $query->where('id_bodega', (int) $this->request->id_bodega);
        }
        if ($this->request->filled('lote_id')) {
            $loteId = (int) $this->request->lote_id;
            $idProductoFiltro = $this->request->filled('id_producto')
                ? (int) $this->request->id_producto
                : null;
            $query->where(function ($q) use ($loteId, $idProductoFiltro) {
                $q->where('lote_id', $loteId)
                    ->orWhere(function ($q2) use ($loteId) {
                        $q2->whereNull('lote_id')
                            ->where(function ($q3) use ($loteId) {
                                $q3->whereHas(
                                    'detalleCompra',
                                    fn ($d) => $d->where('lote_id', $loteId)
                                )->orWhereHas(
                                    'detalleVenta',
                                    fn ($d) => $d->where('lote_id', $loteId)
                                );
                            });
                    })
                    ->orWhere(function ($q2) use ($loteId, $idProductoFiltro) {
                        $q2->where('tipo', ConsignaMovimientoTipoConstants::LIQUIDACION_PROVEEDOR)
                            ->whereHas('compra.detalles', function ($d) use ($loteId, $idProductoFiltro) {
                                $d->where('lote_id', $loteId);
                                if ($idProductoFiltro) {
                                    $d->where('id_producto', $idProductoFiltro);
                                }
                            });
                    });
            });
        }
        if ($this->request->filled('tipo')) {
            $query->where('tipo', $this->request->tipo);
        } else {
            $query->whereIn('tipo', ConsignaMovimientoTipoConstants::tiposKardexPoolProveedor());
        }

        return $query->limit(10000)->get();
    }

    private function documento(ConsignaMovimiento $row): ?string
    {
        if ($row->venta) {
            $nombreDoc = $row->venta->documento?->nombre ?? '';
            $corr = $row->venta->correlativo ?? '';

            return trim($nombreDoc . ($corr !== '' ? ' #' . $corr : '')) ?: null;
        }
        if ($row->compra) {
            return trim(($row->compra->tipo_documento ?? '') . ' ' . ($row->compra->referencia ?? '')) ?: null;
        }

        return null;
    }
}
