<?php

namespace App\Services\Contabilidad;

final class ArbolCuentas
{
    public static function idRequerido(mixed $cuenta): ?int
    {
        if ($cuenta === null || $cuenta === '' || $cuenta === 'all') {
            return null;
        }
        $id = (int) $cuenta;

        return $id > 0 ? $id : null;
    }

    /**
     * @param  iterable<array<string, mixed>|object>  $cuentas
     * @return list<int>
     */
    public static function idsDelArbol(iterable $cuentas, int $raizId): array
    {
        $byParent = [];
        $known = [];
        foreach ($cuentas as $c) {
            $id = (int) (is_array($c) ? ($c['id'] ?? 0) : ($c->id ?? 0));
            if ($id <= 0) {
                continue;
            }
            $padre = is_array($c) ? ($c['id_cuenta_padre'] ?? null) : ($c->id_cuenta_padre ?? null);
            $padre = $padre ? (int) $padre : 0;
            $byParent[$padre][] = $id;
            $known[$id] = true;
        }

        if (!isset($known[$raizId])) {
            return [];
        }

        $out = [$raizId];
        $queue = [$raizId];
        while ($queue) {
            $p = array_shift($queue);
            foreach ($byParent[$p] ?? [] as $hijo) {
                $out[] = $hijo;
                $queue[] = $hijo;
            }
        }

        return $out;
    }
}
