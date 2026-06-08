<?php

namespace App\Exports\Contabilidad\ElSalvador;

use Illuminate\Http\Request;

class DteZipPorSucursalHelper
{
    /**
     * Agrupa en subcarpetas del ZIP cuando no hay sucursal seleccionada (todas).
     */
    public static function agruparPorSucursal(Request $request): bool
    {
        return !$request->filled('id_sucursal');
    }

    /**
     * Ruta del archivo dentro del ZIP (p. ej. "3_Sucursal Centro/ABC.json").
     */
    public static function rutaEnZip(string $fileName, $registro, bool $agruparPorSucursal): string
    {
        if (!$agruparPorSucursal) {
            return $fileName;
        }

        return self::nombreCarpetaSucursal($registro) . '/' . $fileName;
    }

    public static function nombreCarpetaSucursal($registro): string
    {
        $id = (int) ($registro->id_sucursal ?? 0);

        $nombre = null;
        if (isset($registro->sucursal) && $registro->sucursal) {
            $nombre = $registro->sucursal->nombre;
        }

        if (empty($nombre) && !empty($registro->nombre_sucursal)) {
            $nombre = $registro->nombre_sucursal;
        }

        if (empty($nombre)) {
            $nombre = 'Sucursal';
        }

        $nombre = preg_replace('/[\\\\\/:*?"<>|]/', '_', trim($nombre));
        $nombre = $nombre !== '' ? $nombre : 'Sucursal';

        return $id > 0 ? ($nombre) : $nombre;
    }
}
