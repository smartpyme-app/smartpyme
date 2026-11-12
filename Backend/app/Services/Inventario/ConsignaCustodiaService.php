<?php

namespace App\Services\Inventario;

use App\Constants\ConsignaMovimientoTipoConstants;
use App\Models\Inventario\ConsignaCustodia;
use App\Models\Inventario\ConsignaMovimiento;
use App\Models\Ventas\Venta;
use Illuminate\Http\Request;

class ConsignaCustodiaService
{
    public function isLedgerActivo(int $idEmpresa): bool
    {
        return app(ConsignaMovimientoService::class)->isLedgerActivo($idEmpresa);
    }

    public function aplicarDesdeMovimiento(ConsignaMovimiento $movimiento): void
    {
        if (! $this->isLedgerActivo((int) $movimiento->id_empresa)) {
            return;
        }

        $delta = $this->deltaDesdeMovimiento($movimiento);
        if ($delta === null || abs($delta) < 0.00001) {
            return;
        }

        $this->aplicarDelta(
            (int) $movimiento->id_empresa,
            (int) $movimiento->id_cliente,
            (int) $movimiento->id_producto,
            (int) $movimiento->id_bodega,
            $delta
        );
    }

    public function aplicarDelta(
        int $idEmpresa,
        ?int $idCliente,
        int $idProducto,
        int $idBodega,
        float $delta
    ): void {
        if (! $idCliente || abs($delta) < 0.00001) {
            return;
        }

        $fila = ConsignaCustodia::query()->firstOrNew([
            'id_empresa' => $idEmpresa,
            'id_cliente' => $idCliente,
            'id_producto' => $idProducto,
            'id_bodega' => $idBodega,
        ]);

        $nueva = max(0, (float) ($fila->cantidad ?? 0) + $delta);
        if ($nueva <= 0.00001) {
            if ($fila->exists) {
                $fila->delete();
            }

            return;
        }

        $fila->cantidad = $nueva;
        $fila->save();
    }

    public function deltaDesdeMovimiento(ConsignaMovimiento $movimiento): ?float
    {
        if (! $movimiento->id_cliente) {
            return null;
        }

        $cantidad = (float) $movimiento->cantidad;

        return match ($movimiento->tipo) {
            ConsignaMovimientoTipoConstants::ENTREGA_CONSIGNA_PROPIO,
            ConsignaMovimientoTipoConstants::ENTREGA_CONSIGNA_PROVEEDOR => abs($cantidad),
            ConsignaMovimientoTipoConstants::LIQUIDACION_CLIENTE,
            ConsignaMovimientoTipoConstants::DEVOLUCION_CLIENTE => -abs($cantidad),
            ConsignaMovimientoTipoConstants::AJUSTE_CONSIGNA => $cantidad,
            default => null,
        };
    }

    /**
     * ponytail: rebuild O(n) por empresa; upgrade: solo filas afectadas por backfill incremental.
     */
    public function reconstruirDesdeLedger(int $idEmpresa): int
    {
        ConsignaCustodia::query()->forEmpresa($idEmpresa)->delete();

        $movimientos = ConsignaMovimiento::query()
            ->forEmpresa($idEmpresa)
            ->whereNotNull('id_cliente')
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        foreach ($movimientos as $movimiento) {
            $this->aplicarDesdeMovimiento($movimiento);
        }

        return ConsignaCustodia::query()->forEmpresa($idEmpresa)->count();
    }

    public function listar(Request $request, int $idEmpresa)
    {
        $query = ConsignaCustodia::query()
            ->forEmpresa($idEmpresa)
            ->with([
                'producto:id,nombre,codigo',
                'cliente' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'nombre', 'apellido', 'nombre_comercial', 'nombre_empresa'),
                'bodega:id,nombre',
            ])
            ->where('cantidad', '>', 0)
            ->orderBy('id_cliente')
            ->orderBy('id_producto');

        if ($request->filled('id_cliente')) {
            $query->where('id_cliente', (int) $request->id_cliente);
        }
        if ($request->filled('id_producto')) {
            $query->where('id_producto', (int) $request->id_producto);
        }
        if ($request->filled('id_bodega')) {
            $query->where('id_bodega', (int) $request->id_bodega);
        }

        return $query->limit(5000)->get();
    }

  /**
   * Ventas en estado Consigna (remisiones pendientes), no agrupadas por producto.
   */
  public function listarVentasConsigna(Request $request, int $idEmpresa)
    {
        $query = Venta::query()
            ->where('id_empresa', $idEmpresa)
            ->where('estado', 'Consigna')
            ->where('cotizacion', 0)
            ->with([
                'documento:id,nombre',
                'cliente' => fn ($q) => $q->withoutGlobalScopes()->select(
                    'id',
                    'nombre',
                    'apellido',
                    'nombre_comercial',
                    'nombre_empresa'
                ),
            ]);

        if ($request->filled('buscador')) {
            $t = trim((string) $request->buscador);
            $query->where(function ($q) use ($t) {
                $q->where('correlativo', 'like', '%' . $t . '%');
                if (ctype_digit($t)) {
                    $q->orWhere('correlativo', (int) $t);
                }
                $q->orWhereHas('cliente', function ($c) use ($t) {
                        $c->where('nombre', 'like', '%' . $t . '%')
                            ->orWhere('apellido', 'like', '%' . $t . '%')
                            ->orWhere('nombre_comercial', 'like', '%' . $t . '%')
                            ->orWhere('nombre_empresa', 'like', '%' . $t . '%');
                    })
                    ->orWhereHas('documento', fn ($d) => $d->where('nombre', 'like', '%' . $t . '%'));
            });
        }

        $paginate = (int) ($request->input('paginate', 25));
        if ($paginate > 0) {
            return $query
                ->orderByDesc('fecha')
                ->orderByDesc('id')
                ->paginate(min($paginate, 100));
        }

        return $query->orderByDesc('fecha')->orderByDesc('id')->limit(5000)->get();
    }
}
