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

            $clientes = $ventas
                ->map(fn ($v) => $v['cliente'] ?? null)
                ->filter()
                ->unique()
                ->values();

            $fila = [
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'img' => $producto->img,
                'nombre_categoria' => $producto->nombre_categoria,
                'precio' => $detallesGroup[0]->precio,
                'codigo' => $producto->codigo,
                'stock' => $detallesGroup->sum('cantidad'),
                'clientes' => $clientes->all(),
                'clientes_texto' => $clientes->implode('; '),
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

    /**
     * Filas resumen historial: lo que el cliente tiene hoy en remisiones abiertas (estado Consigna).
     *
     * @return list<array<string, mixed>>
     */
    public function resumenFilasCliente(int $idCliente, ?Empresa $empresa): array
    {
        $agruparPorLote = $this->agruparPorProductoYLote($empresa);
        $request = Request::create('/', 'GET');

        $resumen = [];

        foreach ($this->coleccion($request, $empresa) as $fila) {
            $ventasCliente = collect($fila['ventas'] ?? [])->filter(
                fn (array $v) => (int) ($v['id_cliente'] ?? 0) === $idCliente
            );
            if ($ventasCliente->isEmpty()) {
                continue;
            }

            $disponible = (float) $ventasCliente->sum('cantidad');
            if ($disponible <= 0.0001) {
                continue;
            }

            $resumen[] = [
                'id_producto' => (int) ($fila['id'] ?? 0),
                'id_bodega' => 0,
                'codigo' => $fila['codigo'] ?? null,
                'producto' => $fila['nombre'] ?? null,
                'lote_id' => $agruparPorLote ? ($fila['lote_id'] ?? null) : null,
                'lote' => $agruparPorLote ? ($fila['lote_numero'] ?? null) : null,
                'vence' => $agruparPorLote ? ($fila['lote_vencimiento'] ?? null) : null,
                'bodega' => null,
                'enviadas' => $disponible,
                'facturadas' => 0.0,
                'devueltas' => 0.0,
                'disponible' => round($disponible, 4),
                'precio_unitario' => (float) ($fila['precio'] ?? 0),
            ];
        }

        return $resumen;
    }

    /**
     * Una fila por línea de remisión en consigna; Nº CON = correlativo de la venta.
     *
     * @return list<array<string, mixed>>
     */
    public function filasTomaInventarioPorCliente(
        int $idCliente,
        int $idEmpresa,
        ?Empresa $empresa
    ): array {
        $incluirLote = $this->agruparPorProductoYLote($empresa);
        $with = [
            'producto:id,nombre,codigo',
            'venta:id,correlativo,id_bodega,id_empresa',
            'venta.bodega:id,nombre',
        ];
        if ($incluirLote) {
            $with[] = 'lote:id,numero_lote,fecha_vencimiento';
        }

        $detalles = DetalleVenta::query()
            ->whereHas('venta', fn ($q) => $q
                ->where('id_empresa', $idEmpresa)
                ->where('estado', 'Consigna')
                ->where('cotizacion', 0)
                ->where('id_cliente', $idCliente))
            ->with($with)
            ->orderBy('id_venta')
            ->orderBy('id')
            ->get();

        $filas = [];
        foreach ($detalles as $detalle) {
            $qty = (float) $detalle->cantidad;
            if ($qty <= 0.0001) {
                continue;
            }

            $correlativo = $detalle->venta?->correlativo;
            $fila = [
                'numero_consigna' => $correlativo !== null && $correlativo !== '' ? $correlativo : null,
                'id_producto' => (int) $detalle->id_producto,
                'codigo' => $detalle->producto?->codigo,
                'producto' => $detalle->producto?->nombre,
                'bodega' => $detalle->venta?->bodega?->nombre,
                'disponible' => round($qty, 4),
                'precio_unitario' => (float) ($detalle->precio ?? 0),
            ];
            if ($incluirLote) {
                $fila['lote_id'] = $detalle->lote_id ? (int) $detalle->lote_id : null;
                $fila['lote'] = $detalle->lote?->numero_lote;
                $fila['vence'] = $detalle->lote?->fecha_vencimiento?->format('Y-m-d');
            }
            $filas[] = $fila;
        }

        return collect($filas)
            ->sortBy(fn (array $r) => sprintf(
                '%s-%s',
                str_pad((string) ($r['numero_consigna'] ?? ''), 12, '0', STR_PAD_LEFT),
                (string) ($r['producto'] ?? '')
            ))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function lineaVentaConsigna(DetalleVenta $detalle, bool $incluirLoteDetalle): array
    {
        $v = $detalle->venta;
        $doc = $v->documento?->nombre ?? $v->nombre_documento ?? 'Documento';
        $factura = $v->correlativo ? $doc . ' #' . $v->correlativo : $doc;

        $linea = [
            'fecha' => $v->fecha,
            'id_cliente' => $v->id_cliente ? (int) $v->id_cliente : null,
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
