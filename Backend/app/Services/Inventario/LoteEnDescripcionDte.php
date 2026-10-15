<?php

namespace App\Services\Inventario;

use App\Models\Inventario\Lote;
use App\Models\Inventario\Producto;

/**
 * Lote embebido en la descripción de un DTE (p. ej. Mercantil Farmacéutica).
 */
final class LoteEnDescripcionDte
{
    /**
     * @return array{numero_lote: string, cantidad: float, fecha_fabricacion: ?string, fecha_vencimiento: ?string, descripcion: string}|null
     */
    public static function extraer(?string $descripcion): ?array
    {
        $texto = trim((string) $descripcion);
        if ($texto === '') {
            return null;
        }

        $ok = preg_match(
            '/^(.*?)\s*\|\s*Lote:\s*(.+?)\s+Cantidad Lote:\s*([0-9]+(?:[.,][0-9]+)?)(?:\s*\|\s*Fecha fabricación:\s*(\d{4}-\d{2}-\d{2}))?(?:\s*\|\s*Fecha Caducidad:\s*(\d{4}-\d{2}-\d{2}))?\s*$/iu',
            $texto,
            $m
        );
        if ($ok !== 1) {
            return null;
        }

        $numero = trim($m[2]);
        if ($numero === '') {
            return null;
        }

        $nombre = trim($m[1]);

        return [
            'numero_lote' => $numero,
            'cantidad' => (float) str_replace(',', '.', $m[3]),
            'fecha_fabricacion' => ($m[4] ?? '') !== '' ? $m[4] : null,
            'fecha_vencimiento' => ($m[5] ?? '') !== '' ? $m[5] : null,
            'descripcion' => $nombre !== '' ? $nombre : $texto,
        ];
    }

    public static function descripcionProducto(?string $descripcion): string
    {
        $lote = self::extraer($descripcion);

        return $lote['descripcion'] ?? trim((string) $descripcion);
    }

    /**
     * Busca o crea el lote en la bodega y devuelve la asignación de entrada.
     *
     * @param  array{numero_lote?: string, fecha_fabricacion?: ?string, fecha_vencimiento?: ?string}  $pendiente
     * @return array<int, array{lote_id: int, cantidad: float}>
     */
    public static function asignarOCrear(Producto $producto, int $idBodega, float $cantidad, array $pendiente): array
    {
        $numero = trim((string) ($pendiente['numero_lote'] ?? ''));
        if ($numero === '' || $cantidad <= 0 || $idBodega <= 0) {
            return [];
        }

        $lote = Lote::where('id_producto', $producto->id)
            ->where('id_bodega', $idBodega)
            ->where('numero_lote', $numero)
            ->first();

        if (!$lote) {
            $lote = Lote::create([
                'id_producto' => $producto->id,
                'id_bodega' => $idBodega,
                'numero_lote' => $numero,
                'fecha_vencimiento' => $pendiente['fecha_vencimiento'] ?? null,
                'fecha_fabricacion' => $pendiente['fecha_fabricacion'] ?? null,
                'stock' => 0,
                'stock_inicial' => 0,
                'id_empresa' => $producto->id_empresa,
            ]);
        }

        return [[
            'lote_id' => (int) $lote->id,
            'cantidad' => $cantidad,
            'lote' => $lote,
        ]];
    }
}
