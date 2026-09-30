<?php

namespace App\Services\Funcionalidades;

class FuncionalidadJerarquia
{
    // ponytail: un solo nivel. Apagar un padre apaga hijos directos.
    // Si aparece un nieto, recorrer en profundidad antes de apagar al abuelo.
    /**
     * Aplica el cambio de una funcionalidad y arrastra padre o hijos.
     *
     * @param  list<array{id:int, parent_id:?int, activo:bool}>  $items
     * @return list<array{id:int, parent_id:?int, activo:bool}>
     */
    public static function aplicar(array $items, int $id, bool $activo): array
    {
        $byId = self::indexar($items);
        if (! isset($byId[$id])) {
            return array_values($byId);
        }

        $byId[$id]['activo'] = $activo;
        $parentId = $byId[$id]['parent_id'];

        if ($activo && $parentId !== null && isset($byId[$parentId])) {
            $byId[$parentId]['activo'] = true;
        }

        if (! $activo) {
            foreach ($byId as $childId => $child) {
                if ($child['parent_id'] === $id) {
                    $byId[$childId]['activo'] = false;
                }
            }

            if ($parentId !== null && isset($byId[$parentId]) && ! self::hayHijoActivo($byId, $parentId)) {
                $byId[$parentId]['activo'] = false;
            }
        }

        return array_values($byId);
    }

    /**
     * Aplica varios cambios. Un hijo que se enciende prende al padre.
     * Apagar el padre gana sobre encender un hijo en el mismo lote.
     *
     * @param  list<array{id:int, parent_id:?int, activo:bool}>  $items
     * @param  list<array{id:int, activo:bool}>  $cambios
     * @return list<array{id:int, parent_id:?int, activo:bool}>
     */
    public static function aplicarCambios(array $items, array $cambios): array
    {
        $byId = self::indexar($items);
        $ordenados = [];
        foreach (array_values($cambios) as $indice => $cambio) {
            $cambio['id'] = (int) $cambio['id'];
            $cambio['activo'] = (bool) $cambio['activo'];
            $cambio['_orden'] = $indice;
            $ordenados[] = $cambio;
        }

        usort($ordenados, function (array $a, array $b) use ($byId) {
            $prioridad = self::prioridad($a, $byId) <=> self::prioridad($b, $byId);

            return $prioridad !== 0 ? $prioridad : ($a['_orden'] <=> $b['_orden']);
        });

        foreach ($ordenados as $cambio) {
            $items = self::aplicar($items, $cambio['id'], $cambio['activo']);
        }

        return $items;
    }

    /**
     * @param  list<array{id:int, parent_id:?int, activo:bool}>  $items
     * @return array<int, array{id:int, parent_id:?int, activo:bool}>
     */
    private static function indexar(array $items): array
    {
        $byId = [];
        foreach ($items as $item) {
            $itemId = (int) $item['id'];
            $byId[$itemId] = [
                'id' => $itemId,
                'parent_id' => isset($item['parent_id']) && $item['parent_id'] !== null
                    ? (int) $item['parent_id']
                    : null,
                'activo' => (bool) $item['activo'],
            ];
        }

        return $byId;
    }

    /**
     * @param  array<int, array{id:int, parent_id:?int, activo:bool}>  $byId
     */
    private static function hayHijoActivo(array $byId, int $parentId): bool
    {
        foreach ($byId as $child) {
            if ($child['parent_id'] === $parentId && $child['activo']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{id:int, activo:bool}  $cambio
     * @param  array<int, array{id:int, parent_id:?int, activo:bool}>  $byId
     */
    private static function prioridad(array $cambio, array $byId): int
    {
        $esHijo = isset($byId[$cambio['id']]) && $byId[$cambio['id']]['parent_id'] !== null;

        if ($cambio['activo'] && $esHijo) {
            return 0;
        }
        if ($cambio['activo']) {
            return 1;
        }
        if ($esHijo) {
            return 2;
        }

        return 3;
    }
}
