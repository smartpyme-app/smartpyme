<?php

namespace App\Support;

use App\Models\Compras\Compra;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Filtros compartidos entre listado paginado y exportación Excel de compras. */
final class ComprasListQuery
{
    private const ORDEN_PERMITIDO = [
        'id', 'fecha', 'estado', 'referencia', 'total', 'id_proyecto', 'fecha_pago', 'forma_pago',
    ];

    public static function apply(Builder $query, Request $request): Builder
    {
        $orden = in_array((string) ($request->orden ?? ''), self::ORDEN_PERMITIDO, true)
            ? (string) $request->orden
            : 'fecha';
        $direccion = in_array(strtolower((string) ($request->direccion ?? '')), ['asc', 'desc'], true)
            ? strtolower($request->direccion)
            : 'desc';

        return $query
            ->when($request->filled('inicio') && $request->filled('fin'), function (Builder $q) use ($request) {
                $q->whereDate('fecha', '>=', $request->inicio)
                    ->whereDate('fecha', '<=', $request->fin);
            })
            ->when($request->has('recurrente') && $request->recurrente !== '' && $request->recurrente !== null, function (Builder $q) use ($request) {
                $q->where('recurrente', filter_var($request->recurrente, FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->filled('num_identificacion'), function (Builder $q) use ($request) {
                $q->where('num_identificacion', $request->num_identificacion);
            })
            ->when($request->filled('id_sucursal'), function (Builder $q) use ($request) {
                $q->where('id_sucursal', $request->id_sucursal);
            })
            ->when($request->filled('id_bodega'), function (Builder $q) use ($request) {
                $q->where('id_bodega', $request->id_bodega);
            })
            ->when($request->filled('id_usuario'), function (Builder $q) use ($request) {
                $q->where('id_usuario', $request->id_usuario);
            })
            ->when($request->filled('id_proveedor'), function (Builder $q) use ($request) {
                $q->where('id_proveedor', $request->id_proveedor);
            })
            ->when($request->filled('forma_pago'), function (Builder $q) use ($request) {
                $q->where('forma_pago', $request->forma_pago);
            })
            ->when($request->filled('estado'), function (Builder $q) use ($request) {
                $q->where('estado', $request->estado);
            })
            ->when($request->filled('metodo_pago'), function (Builder $q) use ($request) {
                $q->where('metodo_pago', $request->metodo_pago);
            })
            ->when($request->filled('id_proyecto'), function (Builder $q) use ($request) {
                $q->where('id_proyecto', $request->id_proyecto);
            })
            ->when($request->filled('dte') && (string) $request->dte === '0', function (Builder $q) {
                $q->whereNull('sello_mh');
            })
            ->when($request->filled('dte') && (string) $request->dte === '1', function (Builder $q) {
                $q->whereNotNull('sello_mh');
            })
            ->when($request->filled('buscador'), function (Builder $q) use ($request) {
                $texto = '%'.$request->buscador.'%';
                $q->where(function (Builder $inner) use ($texto) {
                    $inner->whereHas('proveedor', function (Builder $p) use ($texto) {
                        $p->where('nombre', 'like', $texto)
                            ->orWhere('nombre_empresa', 'like', $texto)
                            ->orWhere('ncr', 'like', $texto)
                            ->orWhere('nit', 'like', $texto);
                    })->orWhere('referencia', 'like', $texto)
                        ->orWhere('estado', 'like', $texto)
                        ->orWhere('observaciones', 'like', $texto)
                        ->orWhere('forma_pago', 'like', $texto);
                });
            })
            ->where('cotizacion', 0)
            ->orderBy($orden, $direccion)
            ->orderBy('id', 'desc');
    }

    public static function base(Request $request): Builder
    {
        return self::apply(Compra::query(), $request);
    }
}
