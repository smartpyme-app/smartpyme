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

        return $this->payloadDetallePendiente($venta, $primero, count($pendientes));
    }

    /**
     * Misma validación/vinculación automática que la partida individual, para N ventas (p. ej. partida del día).
     *
     * @param  iterable<Venta>  $ventas
     * @return array<string, mixed>|null
     */
    public function resolverAntesDePartidasVentas(iterable $ventas): ?array
    {
        $bloques = [];

        foreach ($ventas as $venta) {
            $payload = $this->resolverAntesDePartida($venta);
            if ($payload !== null) {
                $bloques[] = ['venta' => $venta, 'payload' => $payload];
            }
        }

        if ($bloques === []) {
            return null;
        }

        $totalPendientes = array_sum(array_map(
            static fn (array $b) => (int) ($b['payload']['pendientes'] ?? 1),
            $bloques
        ));

        $primero = $bloques[0];
        /** @var Venta $ventaRef */
        $ventaRef = $primero['venta'];
        $out = $primero['payload'];
        $out['pendientes'] = $totalPendientes;
        $out['referencia_venta'] = $ventaRef->referenciaDocumentoContable();
        $out['error'] = 'Venta ' . $out['referencia_venta'] . ': '
            . ($out['error'] ?? 'Asigne un servicio o producto para continuar.');

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadDetallePendiente(Venta $venta, DetalleVenta $detalle, int $pendientes): array
    {
        return [
            'code' => 'detalle_sin_producto',
            'titulo' => 'Producto no asignado',
            'error' => 'No se encontró un servicio que coincida con la descripción importada. Asigne uno manualmente para generar la partida.',
            'id_venta' => $venta->id,
            'detalle' => [
                'id' => $detalle->id,
                'descripcion' => $detalle->descripcion,
                'id_producto' => (int) $detalle->id_producto,
            ],
            'sugerencias' => $this->sugerenciasPara($detalle->descripcion ?? ''),
            'pendientes' => $pendientes,
            'referencia_venta' => $venta->referenciaDocumentoContable(),
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
