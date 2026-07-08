<?php

namespace App\Services\Audit;

use App\Models\Compras\Compra;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Compras\Proveedores\Proveedor;
use App\Models\Contabilidad\Partidas\Partida;
use App\Models\CotizacionVenta;
use App\Models\Inventario\Ajuste;
use App\Models\Inventario\Entradas\Entrada;
use App\Models\Inventario\Paquete;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Salidas\Salida;
use App\Models\Inventario\Traslados\Traslado;
use App\Models\Admin\FormaDePago;
use App\Models\Admin\Impuesto;
use App\Models\Admin\Sucursal;
use App\Models\OrdenCompra;
use App\Models\Planilla\Planilla;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\PedidoRestaurante;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Orden_Produccion\OrdenProduccion;
use App\Models\Ventas\Venta;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class AuditQueryService
{
    public function paginate(array $filters, bool $crossTenant = false): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? $filters['paginate'] ?? 25), 50);

        $query = \App\Models\Audit\Audit::query()
            ->with(['user:id,name', 'empresa:id,nombre'])
            ->when($crossTenant, fn ($q) => $q->withoutGlobalScope('empresa'))
            ->when(! empty($filters['id_empresa']), fn ($q) => $q->where('id_empresa', $filters['id_empresa']))
            ->when(! empty($filters['module']), fn ($q) => $q->where('module', $filters['module']))
            ->when(! empty($filters['user_id']), fn ($q) => $q->where('user_id', $filters['user_id']))
            ->when(! empty($filters['fecha_inicio']), fn ($q) => $q->whereDate('created_at', '>=', $filters['fecha_inicio']))
            ->when(! empty($filters['fecha_fin']), fn ($q) => $q->whereDate('created_at', '<=', $filters['fecha_fin']))
            ->orderByDesc('created_at');

        return $query->paginate($perPage);
    }

    /** @return array<int, string> */
    public function productNamesForPage(LengthAwarePaginator $paginator): array
    {
        $ids = collect($paginator->items())->flatMap(function ($audit) {
            $new = is_array($audit->new_values) ? $audit->new_values : [];
            $old = is_array($audit->old_values) ? $audit->old_values : [];

            return array_filter([
                $new['id_producto'] ?? null,
                $old['id_producto'] ?? null,
            ]);
        })->unique()->values()->all();

        if ($ids === []) {
            return [];
        }

        return Producto::withoutGlobalScopes()
            ->whereIn('id', $ids)
            ->pluck('nombre', 'id')
            ->all();
    }

    /** @return array<string, string> keys: "Fully\\Qualified\\Model:123" */
    public function documentReferencesForPage(LengthAwarePaginator $paginator): array
    {
        $byType = [];
        foreach ($paginator->items() as $audit) {
            if ($audit->auditable_type && $audit->auditable_id) {
                $byType[$audit->auditable_type][] = (int) $audit->auditable_id;
            }
        }

        $refs = [];
        foreach ($byType as $type => $ids) {
            foreach ($this->loadReferencesForType($type, array_unique($ids)) as $id => $label) {
                $refs["{$type}:{$id}"] = $label;
            }
        }

        return $refs;
    }

    /** @return array<int, string> */
    private function loadReferencesForType(string $type, array $ids): array
    {
        if ($ids === [] || ! class_exists($type)) {
            return [];
        }

        /** @var class-string<Model> $type */
        return match ($type) {
            Venta::class, Compra::class, CotizacionVenta::class, Partida::class
                => $this->pluckColumn($type, $ids, 'correlativo'),
            Gasto::class => $this->pluckFirstColumn($type, $ids, ['referencia', 'concepto', 'num_identificacion']),
            OrdenCompra::class, OrdenProduccion::class, Planilla::class
                => $this->pluckFirstColumn($type, $ids, ['correlativo', 'codigo']),
            Cliente::class => $this->loadPersonReferences($type, $ids, true),
            Proveedor::class => $this->loadPersonReferences($type, $ids, false),
            Paquete::class => $this->pluckFirstColumn($type, $ids, ['num_guia', 'wr', 'num_seguimiento']),
            PedidoRestaurante::class => $this->loadPedidoReferences($ids),
            Comanda::class => $this->pluckColumn($type, $ids, 'numero_comanda'),
            Producto::class => $this->pluckFirstColumn($type, $ids, ['codigo', 'nombre']),
            Entrada::class, Salida::class, Traslado::class
                => $this->pluckFirstColumn($type, $ids, ['concepto']),
            Sucursal::class, FormaDePago::class, Impuesto::class
                => $this->pluckFirstColumn($type, $ids, ['nombre']),
            Ajuste::class => [],
            default => [],
        };
    }

    /** @param class-string<Model> $modelClass @return array<int, string> */
    private function pluckColumn(string $modelClass, array $ids, string $column): array
    {
        return $modelClass::withoutGlobalScopes()
            ->whereIn('id', $ids)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->pluck($column, 'id')
            ->all();
    }

    /** @param class-string<Model> $modelClass @return array<int, string> */
    private function pluckFirstColumn(string $modelClass, array $ids, array $columns): array
    {
        $rows = $modelClass::withoutGlobalScopes()
            ->whereIn('id', $ids)
            ->get(array_merge(['id'], $columns));

        $out = [];
        foreach ($rows as $row) {
            foreach ($columns as $col) {
                $val = $row->{$col} ?? null;
                if ($val !== null && $val !== '') {
                    $out[$row->id] = (string) $val;
                    break;
                }
            }
        }

        return $out;
    }

    /** @param class-string<Model> $modelClass @return array<int, string> */
    private function loadPersonReferences(string $modelClass, array $ids, bool $isCliente): array
    {
        $columns = $isCliente
            ? ['id', 'nombre', 'apellido', 'nombre_empresa']
            : ['id', 'nombre', 'apellido', 'nombre_empresa'];

        $out = [];
        foreach ($modelClass::withoutGlobalScopes()->whereIn('id', $ids)->get($columns) as $row) {
            $name = trim((string) ($row->nombre_empresa ?: trim("{$row->nombre} {$row->apellido}")));
            if ($name !== '') {
                $out[$row->id] = $name;
            }
        }

        return $out;
    }

    /** @return array<int, string> */
    private function loadPedidoReferences(array $ids): array
    {
        $out = [];
        foreach (PedidoRestaurante::withoutGlobalScopes()->whereIn('id', $ids)->get(['id', 'referencia_externa']) as $row) {
            $ref = trim((string) ($row->referencia_externa ?? ''));
            $out[$row->id] = $ref !== '' ? $ref : (string) $row->id;
        }

        return $out;
    }
}
