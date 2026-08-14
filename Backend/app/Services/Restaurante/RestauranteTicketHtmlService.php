<?php

namespace App\Services\Restaurante;

use App\Models\Admin\Empresa;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\PreCuenta;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;

/**
 * Render HTML de tickets. Cache es aceleración, no SoT.
 */
class RestauranteTicketHtmlService
{
    public function cacheKeyComanda(int $comandaId): string
    {
        return 'rest:ticket:comanda:'.$comandaId;
    }

    public function cacheKeyPreCuenta(int $preCuentaId): string
    {
        return 'rest:ticket:precuenta:'.$preCuentaId;
    }

    public function rememberComandaHtml(int $comandaId, int $empresaId): string
    {
        $ttl = (int) config('restaurante.ticket_cache_ttl', 300);
        $key = $this->cacheKeyComanda($comandaId);

        return Cache::remember($key, $ttl, fn () => $this->renderComandaHtml($comandaId, $empresaId));
    }

    public function rememberPreCuentaHtml(int $preCuentaId, int $empresaId): string
    {
        $ttl = (int) config('restaurante.ticket_cache_ttl', 300);
        $key = $this->cacheKeyPreCuenta($preCuentaId);

        return Cache::remember($key, $ttl, fn () => $this->renderPreCuentaHtml($preCuentaId, $empresaId));
    }

    public function renderComandaHtml(int $comandaId, int $empresaId): string
    {
        $comanda = Comanda::where('id_empresa', $empresaId)
            ->with([
                'sesion.mesa',
                'sesion.mesero',
                'pedido',
                'detalles.ordenDetalle' => fn ($q) => $q->withTrashed()->with('producto'),
                'detalles.pedidoDetalle.producto',
            ])
            ->findOrFail($comandaId);

        $empresa = Empresa::find($empresaId);

        return View::make('restaurante.comanda-ticket', compact('comanda', 'empresa'))->render();
    }

    public function renderPreCuentaHtml(int $preCuentaId, int $empresaId): string
    {
        $preCuenta = PreCuenta::whereHas('sesion', fn ($q) => $q->where('id_empresa', $empresaId))
            ->with(['sesion.mesa', 'sesion.mesero', 'sesion.ordenDetalle.producto', 'ordenDetalles.producto'])
            ->findOrFail($preCuentaId);

        $items = $preCuenta->ordenDetalles->isNotEmpty()
            ? $preCuenta->ordenDetalles
            : $preCuenta->sesion->ordenDetalle;

        $itemsAgrupados = $this->lineasAgrupadasParaVista($items);
        $empresa = Empresa::find($empresaId);

        return View::make('restaurante.pre-cuenta-ticket', [
            'preCuenta' => $preCuenta,
            'items' => $itemsAgrupados,
            'empresa' => $empresa,
        ])->render();
    }

    /**
     * Agrupa líneas para ticket de pre-cuenta (misma semántica que PreCuentaController).
     *
     * @param  iterable<mixed>  $items
     * @return array<int, object>
     */
    public function lineasAgrupadasParaVista(iterable $items): array
    {
        return collect($items)
            ->groupBy(function ($i) {
                $n = $i->notas ?? '';
                $nk = trim((string) $n) === '' ? '' : trim((string) $n);

                return $i->producto_id.'|'.round((float) $i->precio_unitario, 2).'|'.$nk;
            })
            ->map(function ($grupo) {
                $first = $grupo->sortBy('id')->first();
                $cant = (float) $grupo->sum(fn ($x) => $this->cantidadLineaParaPreCuenta($x));

                return (object) [
                    'cantidad' => $cant,
                    'precio_unitario' => $first->precio_unitario,
                    'notas' => $first->notas,
                    'producto' => $first->producto ?? null,
                    'producto_id' => $first->producto_id,
                ];
            })
            ->values()
            ->all();
    }

    private function cantidadLineaParaPreCuenta($ordenDetalleRow): float
    {
        $pivot = $ordenDetalleRow->pivot ?? null;
        if ($pivot && ($pivot->cantidad !== null && $pivot->cantidad !== '')) {
            return round((float) $pivot->cantidad, 4);
        }

        return round((float) $ordenDetalleRow->cantidad, 4);
    }
}
