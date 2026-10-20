<?php

namespace App\Services\Ventas;

use App\Models\Inventario\Producto;
use App\Models\Ventas\Detalle as DetalleVenta;
use App\Models\Ventas\Venta;
use App\Support\Ventas\ComparadorNombreProducto;
use Illuminate\Support\Collection;

class VincularProductoDetalleVentaService
{
    private const MAX_SUGERENCIAS = 5;

    /** @var Collection<int, Producto>|null */
    private ?Collection $serviciosCache = null;

    /**
     * Vincula por nombre exacto (normalizado) y devuelve payload de error si aún hay pendientes.
     *
     * @return array<string, mixed>|null
     */
    public function resolverAntesDePartida(Venta $venta): ?array
    {
        return $this->resolverAntesDePartidaConServicios($venta, $this->serviciosActivos());
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
        $servicios = $this->serviciosActivos();

        foreach ($ventas as $venta) {
            $payload = $this->resolverAntesDePartidaConServicios($venta, $servicios);
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
     * @return array<string, mixed>|null
     */
    private function resolverAntesDePartidaConServicios(Venta $venta, Collection $servicios): ?array
    {
        $detalles = DetalleVenta::query()
            ->where('id_venta', $venta->id)
            ->get();

        $pendientes = [];

        foreach ($detalles as $detalle) {
            if (!$this->detalleRequiereProducto($detalle)) {
                continue;
            }

            $match = $this->buscarCoincidenciaExacta($detalle->descripcion ?? '', $servicios);
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

        return $this->payloadDetallePendiente($venta, $primero, count($pendientes), $servicios);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadDetallePendiente(
        Venta $venta,
        DetalleVenta $detalle,
        int $pendientes,
        Collection $servicios
    ): array {
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
            'sugerencias' => $this->sugerenciasPara($detalle->descripcion ?? '', $servicios),
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

    /**
     * @param  Collection<int, Producto>  $servicios
     */
    private function buscarCoincidenciaExacta(string $descripcion, Collection $servicios): ?Producto
    {
        $needle = ComparadorNombreProducto::normalize($descripcion);
        if ($needle === '') {
            return null;
        }

        $matches = $servicios->filter(
            fn (Producto $p) => ComparadorNombreProducto::coincideExacto($descripcion, $p->nombre)
        )->values();

        // ponytail: si hay duplicados con el mismo nombre normalizado, el primero basta para contabilizar
        return $matches->first();
    }

    /**
     * @return list<array{id: int, nombre: string, codigo: string|null, tipo: string}>
     */
    public function sugerenciasPara(string $descripcion, ?Collection $servicios = null): array
    {
        $needle = ComparadorNombreProducto::normalize($descripcion);
        if ($needle === '') {
            return [];
        }

        $pool = $servicios ?? $this->serviciosActivos();
        $candidatos = $this->filtrarCandidatosSugerencia($pool, $needle);

        $scored = [];
        foreach ($candidatos as $servicio) {
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

    /**
     * @return Collection<int, Producto>
     */
    private function serviciosActivos(): Collection
    {
        if ($this->serviciosCache !== null) {
            return $this->serviciosCache;
        }

        $this->serviciosCache = Producto::query()
            ->where('tipo', 'Servicio')
            ->where('enable', true)
            ->get(['id', 'nombre', 'codigo', 'tipo']);

        return $this->serviciosCache;
    }

    /**
     * Reduce similar_text a un subconjunto acotado.
     *
     * @param  Collection<int, Producto>  $servicios
     * @return Collection<int, Producto>
     */
    private function filtrarCandidatosSugerencia(Collection $servicios, string $needle): Collection
    {
        $tokens = array_values(array_filter(explode(' ', $needle), static fn (string $t) => mb_strlen($t) >= 3));
        $prefiltrados = $servicios->filter(function (Producto $servicio) use ($needle, $tokens) {
            $haystack = ComparadorNombreProducto::normalize($servicio->nombre);
            if ($haystack === '') {
                return false;
            }
            if (str_contains($haystack, $needle) || str_contains($needle, $haystack)) {
                return true;
            }
            foreach ($tokens as $token) {
                if (str_contains($haystack, $token)) {
                    return true;
                }
            }

            return false;
        });

        if ($prefiltrados->isNotEmpty()) {
            return $prefiltrados->take(120);
        }

        return $servicios->take(120);
    }
}
