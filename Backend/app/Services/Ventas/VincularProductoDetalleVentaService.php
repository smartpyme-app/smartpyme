<?php

namespace App\Services\Ventas;

use App\Models\Inventario\Producto;
use App\Models\Ventas\Detalle as DetalleVenta;
use App\Models\Ventas\Venta;
use App\Support\Ventas\ComparadorNombreProducto;

class VincularProductoDetalleVentaService
{
    private const MAX_SUGERENCIAS = 5;

    /**
     * Vincula por nombre exacto (normalizado) y devuelve payload de error si aún hay pendientes.
     *
     * @return array<string, mixed>|null
     */
    public function resolverAntesDePartida(Venta $venta): ?array
    {
        $detalles = DetalleVenta::query()
            ->where('id_venta', $venta->id)
            ->get();

        $pendientes = [];

        foreach ($detalles as $detalle) {
            if (!$this->detalleRequiereProducto($detalle)) {
                continue;
            }

            $match = $this->buscarCoincidenciaExacta($detalle->descripcion ?? '');
            if ($match !== null) {
                $detalle->id_producto = $match->id;
                $detalle->save();
                continue;
            }

            $pendientes[] = $detalle;
        }

        if ($pendientes === []) {
            return null;
        }

        /** @var DetalleVenta $primero */
        $primero = $pendientes[0];

        return [
            'code' => 'detalle_sin_producto',
            'titulo' => 'Producto no asignado',
            'error' => 'No se encontró un servicio que coincida con la descripción importada. Asigne uno manualmente para generar la partida.',
            'id_venta' => $venta->id,
            'detalle' => [
                'id' => $primero->id,
                'descripcion' => $primero->descripcion,
                'id_producto' => (int) $primero->id_producto,
            ],
            'sugerencias' => $this->sugerenciasPara($primero->descripcion ?? ''),
            'pendientes' => count($pendientes),
        ];
    }

    public function detalleRequiereProducto(DetalleVenta $detalle): bool
    {
        if ((int) $detalle->id_producto === 0) {
            return true;
        }

        return !$detalle->producto;
    }

    private function buscarCoincidenciaExacta(string $descripcion): ?Producto
    {
        $needle = ComparadorNombreProducto::normalize($descripcion);
        if ($needle === '') {
            return null;
        }

        $candidatos = Producto::query()
            ->where('tipo', 'Servicio')
            ->where('enable', true)
            ->get(['id', 'nombre', 'codigo', 'tipo']);

        $matches = $candidatos->filter(
            fn (Producto $p) => ComparadorNombreProducto::coincideExacto($descripcion, $p->nombre)
        )->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * @return list<array{id: int, nombre: string, codigo: string|null, tipo: string}>
     */
    public function sugerenciasPara(string $descripcion): array
    {
        $needle = ComparadorNombreProducto::normalize($descripcion);
        if ($needle === '') {
            return [];
        }

        $servicios = Producto::query()
            ->where('tipo', 'Servicio')
            ->where('enable', true)
            ->get(['id', 'nombre', 'codigo', 'tipo']);

        $scored = [];
        foreach ($servicios as $servicio) {
            $haystack = ComparadorNombreProducto::normalize($servicio->nombre);
            if ($haystack === '' || $haystack === $needle) {
                continue;
            }

            $score = 0.0;
            if (str_contains($haystack, $needle) || str_contains($needle, $haystack)) {
                $score = 85.0;
            } else {
                similar_text($haystack, $needle, $score);
            }

            if ($score < 35.0) {
                continue;
            }

            $scored[] = [
                'score' => $score,
                'item' => [
                    'id' => (int) $servicio->id,
                    'nombre' => $servicio->nombre,
                    'codigo' => $servicio->codigo,
                    'tipo' => (string) $servicio->tipo,
                ],
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_values(array_map(
            fn (array $row) => $row['item'],
            array_slice($scored, 0, self::MAX_SUGERENCIAS)
        ));
    }
}
