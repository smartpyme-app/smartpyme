<?php

namespace App\Services\Incentivos;

class ReglaAlcance
{
    /**
     * @param  array<int, object>  $reglas
     * @return array<int, object>
     */
    public function aplicables(array $reglas, int $idVendedor): array
    {
        $filtradas = array_values(array_filter(
            $reglas,
            fn (object $regla): bool => $this->cubre($regla, $idVendedor)
        ));
        $reemplaza = false;
        foreach ($filtradas as $regla) {
            if (($regla->alcance ?? 'global') !== 'global' && ! empty($regla->reemplaza_global)) {
                $reemplaza = true;
                break;
            }
        }

        return $reemplaza
            ? array_values(array_filter($filtradas, fn (object $regla) => ($regla->alcance ?? 'global') !== 'global'))
            : $filtradas;
    }

    private function cubre(object $regla, int $idVendedor): bool
    {
        return ($regla->alcance ?? 'global') === 'global'
            || in_array($idVendedor, array_map('intval', (array) ($regla->id_vendedores ?? [])), true);
    }
}
