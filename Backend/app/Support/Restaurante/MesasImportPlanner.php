<?php

namespace App\Support\Restaurante;

final class MesasImportPlanner
{
    /**
     * @param  iterable<int, object{id:int|string, nombre:string}>  $zonas
     * @return array{map: array<string, object>, errors: list<string>}
     */
    public static function indexZonas(iterable $zonas): array
    {
        $map = [];
        $errors = [];
        $seen = [];

        foreach ($zonas as $zona) {
            $nombre = trim((string) $zona->nombre);
            if ($nombre === '') {
                continue;
            }

            if (isset($seen[$nombre])) {
                $errors[] = "Zona ambigua: \"{$nombre}\"";
                unset($map[$nombre]);
                continue;
            }

            $seen[$nombre] = true;
            $map[$nombre] = $zona;
        }

        if ($errors !== []) {
            $map = [];
        }

        return compact('map', 'errors');
    }

    /**
     * @param  list<array{fila:int, numero:?string, capacidad:mixed, zona_nombre:?string, orden:mixed}>  $rows
     * @param  array<string, object{id:int|string, nombre?:string}>  $zonaMap
     * @param  array<string, bool>  $existingKeys  keys "zonaId|numero"
     * @return array{crear: list<array>, omitir: list<array>, errores: list<array>}
     */
    public static function plan(array $rows, array $zonaMap, array $existingKeys): array
    {
        $crear = [];
        $omitir = [];
        $errores = [];

        foreach ($rows as $row) {
            $fila = (int) ($row['fila'] ?? 0);
            $numero = trim((string) ($row['numero'] ?? ''));
            $zonaNombre = trim((string) ($row['zona_nombre'] ?? ''));

            if ($numero === '') {
                $errores[] = ['fila' => $fila, 'zona' => $zonaNombre, 'motivo' => 'Número de mesa vacío'];
                continue;
            }

            if ($zonaNombre === '') {
                $errores[] = ['fila' => $fila, 'zona' => '', 'motivo' => 'Nombre de zona vacío'];
                continue;
            }

            if (! isset($zonaMap[$zonaNombre])) {
                $errores[] = ['fila' => $fila, 'zona' => $zonaNombre, 'motivo' => 'Zona no encontrada'];
                continue;
            }

            $capacidad = $row['capacidad'] ?? null;
            if ($capacidad === null || $capacidad === '') {
                $capacidad = 4;
            }
            if (! is_numeric($capacidad) || (int) $capacidad < 1) {
                $errores[] = ['fila' => $fila, 'zona' => $zonaNombre, 'motivo' => 'Capacidad inválida'];
                continue;
            }

            $orden = $row['orden'] ?? null;
            if ($orden === null || $orden === '') {
                $orden = 0;
            }
            if (! is_numeric($orden) || (int) $orden < 0) {
                $errores[] = ['fila' => $fila, 'zona' => $zonaNombre, 'motivo' => 'Orden inválido'];
                continue;
            }

            $zona = $zonaMap[$zonaNombre];
            $key = $zona->id.'|'.$numero;
            $payload = [
                'numero' => $numero,
                'capacidad' => (int) $capacidad,
                'orden' => (int) $orden,
                'zona_id' => (int) $zona->id,
                'zona' => $zonaNombre,
                'fila' => $fila,
            ];

            if (isset($existingKeys[$key])) {
                $omitir[] = $payload;
                continue;
            }

            $existingKeys[$key] = true;
            $crear[] = $payload;
        }

        return compact('crear', 'omitir', 'errores');
    }
}
