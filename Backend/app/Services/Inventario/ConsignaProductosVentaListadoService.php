<?php

namespace App\Services\Inventario;

use App\Models\Admin\Empresa;
use App\Models\Ventas\Detalle as DetalleVenta;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;

class ConsignaProductosVentaListadoService
{
    public function agruparPorProductoYLote(?Empresa $empresa): bool
    {
        return $empresa?->isModuloConsignacionesLedgerActivo()
            && $empresa?->isLotesActivo();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function coleccion(Request $request, ?Empresa $empresa): Collection
    {
        $agruparPorLote = $this->agruparPorProductoYLote($empresa);

        $relaciones = ['producto.categoria', 'venta.documento'];
        if ($agruparPorLote) {
            $relaciones[] = 'lote';
        }

        $detallesDeVenta = DetalleVenta::query()
            ->whereHas('venta', fn ($q) => $q->where('estado', 'Consigna')->where('cotizacion', 0))
            ->when($request->filled('buscador'), function ($query) use ($request) {
                $t = trim((string) $request->buscador);
                $query->where(function ($w) use ($t) {
                    $w->whereHas('venta', function ($v) use ($t) {
                        $v->where(function ($vq) use ($t) {
                            $vq->where('correlativo', 'like', '%' . $t . '%');
                            if (ctype_digit($t)) {
                                $vq->orWhere('correlativo', (int) $t);
                            }
                            $vq->orWhere('nombre_cliente', 'like', '%' . $t . '%')
                                ->orWhereHas('documento', fn ($d) => $d->where('nombre', 'like', '%' . $t . '%'));
                        });
                    })->orWhereHas('producto', function ($p) use ($t) {
                        $p->where('nombre', 'like', '%' . $t . '%')
                            ->orWhere('codigo', 'like', '%' . $t . '%');
                    });
                    if ($t !== '') {
                        $w->orWhereHas('lote', fn ($l) => $l->where('numero_lote', 'like', '%' . $t . '%'));
                    }
                });
            })
            ->when($request->filled('categoria'), function ($query) use ($request) {
                $cat = trim((string) $request->categoria);
                if ($cat !== '') {
                    $query->whereHas('producto.categoria', fn ($q) => $q->where('nombre', $cat));
                }
            })
            ->with($relaciones)
            ->get()
            ->groupBy(function (DetalleVenta $detalle) use ($agruparPorLote) {
                if ($agruparPorLote) {
                    return $detalle->id_producto . ':' . ($detalle->lote_id ?? 0);
                }

                return (string) $detalle->id_producto;
            });

        $filas = collect();

        foreach ($detallesDeVenta as $detallesGroup) {
            $ventas = collect();
            foreach ($detallesGroup as $detalle) {
                $ventas->push($this->lineaVentaConsigna($detalle, $agruparPorLote));
            }

            $producto = $detallesGroup[0]->producto;
            if (! $producto) {
                continue;
            }

            $loteId = $agruparPorLote ? ($detallesGroup[0]->lote_id ? (int) $detallesGroup[0]->lote_id : null) : null;
            $lote = $agruparPorLote ? $detallesGroup[0]->lote : null;

            $fila = [
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'img' => $producto->img,
                'nombre_categoria' => $producto->nombre_categoria,
                'precio' => $detallesGroup[0]->precio,
                'codigo' => $producto->codigo,
                'stock' => $detallesGroup->sum('cantidad'),
                'ventas' => $ventas->values()->all(),
                'agrupar_por_lote' => $agruparPorLote,
                'row_key' => $agruparPorLote
                    ? $producto->id . ':' . ($loteId ?? 0)
                    : (string) $producto->id,
            ];

            if ($agruparPorLote) {
                $fila['lote_id'] = $loteId;
                $fila['lote_numero'] = $lote?->numero_lote;
                $fila['lote_vencimiento'] = $lote?->fecha_vencimiento?->format('Y-m-d');
                $fila['lote_estado_vencimiento'] = $lote?->estado_vencimiento;
            }

            $filas->push($fila);
        }

        return $filas->sortBy(fn ($row) => ($row['nombre'] ?? '') . ($row['lote_numero'] ?? ''))->values();
    }

    /** @return array<string, mixed> */
    private function lineaVentaConsigna(DetalleVenta $detalle, bool $incluirLoteDetalle): array
    {
        $v = $detalle->venta;
        $doc = $v->documento?->nombre ?? $v->nombre_documento ?? 'Documento';
        $factura = $v->correlativo ? $doc . ' #' . $v->correlativo : $doc;

        $linea = [
            'fecha' => $v->fecha,
            'cliente' => $v->nombre_cliente,
            'cantidad' => $detalle->cantidad,
            'id' => $v->id,
            'nombre_documento' => $doc,
            'correlativo' => $v->correlativo,
            'factura' => $factura,
            'fecha_pago' => $v->fecha_pago,
            'uuid' => Crypt::encrypt($v->id),
        ];

        if ($incluirLoteDetalle && $detalle->lote_id) {
            $linea['lote_id'] = (int) $detalle->lote_id;
            $linea['lote_numero'] = $detalle->lote?->numero_lote;
            $linea['lote_vencimiento'] = $detalle->lote?->fecha_vencimiento?->format('Y-m-d');
        }

        return $linea;
    }
}
