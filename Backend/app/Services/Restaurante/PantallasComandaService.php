<?php

namespace App\Services\Restaurante;

use App\Models\Inventario\Producto;
use App\Models\Restaurante\PantallaRestaurante;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class PantallasComandaService
{
    public function tablaLista(): bool
    {
        return Schema::hasTable('restaurante_pantallas')
            && Schema::hasTable('producto_restaurante_pantalla')
            && Schema::hasTable('restaurante_envio_pantalla')
            && Schema::hasColumn('comandas_restaurante', 'pantalla_id');
    }

    public function asegurarDefecto(int $idEmpresa): void
    {
        if (! $this->tablaLista()) {
            return;
        }
        if (PantallaRestaurante::where('id_empresa', $idEmpresa)->exists()) {
            return;
        }

        PantallaRestaurante::create([
            'id_empresa' => $idEmpresa,
            'nombre' => 'Cocina',
            'orden' => 0,
            'activo' => true,
        ]);
        PantallaRestaurante::create([
            'id_empresa' => $idEmpresa,
            'nombre' => 'Barra',
            'orden' => 1,
            'activo' => true,
        ]);
    }

    /**
     * @param  array<int, mixed>  $ids
     */
    public function syncProducto(Producto $producto, array $ids): void
    {
        $this->asegurarDefecto((int) $producto->id_empresa);
        $validos = PantallaRestaurante::where('id_empresa', $producto->id_empresa)
            ->whereIn('id', array_map('intval', $ids))
            ->pluck('id')
            ->all();
        $producto->pantallasComanda()->sync($validos);
    }

    /**
     * @param  iterable<int, object>  $items
     * @return array<int, array{pantalla: PantallaRestaurante, items: array<int, object>}>
     */
    public function agruparPendientes(iterable $items, string $columnaEnvio): array
    {
        $lista = collect($items);
        $ids = $lista->pluck('id')->map(fn ($id) => (int) $id)->all();
        $enviados = [];
        if ($ids !== []) {
            $rows = DB::table('restaurante_envio_pantalla')
                ->whereIn($columnaEnvio, $ids)
                ->get(['pantalla_id', $columnaEnvio]);
            foreach ($rows as $row) {
                $enviados[(int) $row->{$columnaEnvio}][] = (int) $row->pantalla_id;
            }
        }

        $grupos = [];
        foreach ($lista as $item) {
            $producto = $item->producto ?? null;
            if (! $producto || ! $producto->genera_comanda) {
                continue;
            }
            $ya = $enviados[(int) $item->id] ?? [];
            foreach ($this->pantallasDe($producto) as $pantalla) {
                if (in_array((int) $pantalla->id, $ya, true)) {
                    continue;
                }
                $grupos[$pantalla->id]['pantalla'] = $pantalla;
                $grupos[$pantalla->id]['items'][] = $item;
            }
        }

        return $grupos;
    }

    /**
     * @param  array<int, int>  $detalleIds
     */
    public function marcarEnviados(PantallaRestaurante $pantalla, array $detalleIds, string $columnaEnvio): void
    {
        if ($detalleIds === []) {
            return;
        }

        $ahora = now();
        foreach ($detalleIds as $detalleId) {
            $existe = DB::table('restaurante_envio_pantalla')
                ->where('pantalla_id', $pantalla->id)
                ->where($columnaEnvio, $detalleId)
                ->lockForUpdate()
                ->exists();
            if ($existe) {
                throw new RuntimeException(
                    "Conflicto al enviar comanda ({$pantalla->nombre}): otro proceso ya marcó uno o más ítems."
                );
            }
            DB::table('restaurante_envio_pantalla')->insert([
                'pantalla_id' => $pantalla->id,
                $columnaEnvio => $detalleId,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        // ponytail: enviado_cocina/enviado_barra siguen siendo el candado de “ya se envió”
        // para fusión y edición. La membresía real es restaurante_envio_pantalla.
        $flag = $this->esBarra($pantalla) ? 'enviado_barra' : 'enviado_cocina';
        $tabla = $columnaEnvio === 'orden_detalle_id'
            ? 'orden_detalle_restaurante'
            : 'restaurante_pedido_detalles';
        DB::table($tabla)->whereIn('id', $detalleIds)->update([$flag => true]);
    }

    public function destinoLegacy(PantallaRestaurante $pantalla): string
    {
        return $this->esBarra($pantalla) ? 'barra' : 'cocina';
    }

    /**
     * @return Collection<int, PantallaRestaurante>
     */
    public function pantallasDe(Producto $producto): Collection
    {
        $asignadas = $producto->relationLoaded('pantallasComanda')
            ? $producto->pantallasComanda
            : $producto->pantallasComanda()->get();

        if ($asignadas->isNotEmpty()) {
            return $asignadas->filter(fn (PantallaRestaurante $p) => $p->activo)->sortBy('orden')->values();
        }

        return $this->pantallasLegacy((int) $producto->id_empresa, $producto->destino_comanda);
    }

    /**
     * @return Collection<int, PantallaRestaurante>
     */
    private function pantallasLegacy(int $idEmpresa, ?string $destino): Collection
    {
        $d = strtolower(trim((string) $destino));
        $nombres = $d === 'barra' ? ['Barra'] : ($d === 'ambos' ? ['Cocina', 'Barra'] : ['Cocina']);

        return PantallaRestaurante::where('id_empresa', $idEmpresa)
            ->where('activo', true)
            ->whereIn('nombre', $nombres)
            ->orderBy('orden')
            ->get();
    }

    private function esBarra(PantallaRestaurante $pantalla): bool
    {
        return strtolower(trim($pantalla->nombre)) === 'barra';
    }
}
