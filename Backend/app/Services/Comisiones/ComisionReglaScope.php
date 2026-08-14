<?php

namespace App\Services\Comisiones;

use App\Models\Comisiones\ComisionRegla;

class ComisionReglaScope
{
    /**
     * @param  array<int, object>  $reglas
     * @return array<int, object>
     */
    public function aplicables(array $reglas, int $idVendedor): array
    {
        $filtradas = [];
        foreach ($reglas as $regla) {
            if ($this->cubre($regla, $idVendedor)) {
                $filtradas[] = $regla;
            }
        }

        $reemplaza = false;
        foreach ($filtradas as $regla) {
            $alcance = (string) ($regla->alcance ?? ComisionRegla::ALCANCE_GLOBAL);
            if ($alcance !== ComisionRegla::ALCANCE_GLOBAL && ! empty($regla->reemplaza_global)) {
                $reemplaza = true;
                break;
            }
        }

        if (! $reemplaza) {
            return array_values($filtradas);
        }

        return array_values(array_filter(
            $filtradas,
            fn ($r) => ($r->alcance ?? ComisionRegla::ALCANCE_GLOBAL) !== ComisionRegla::ALCANCE_GLOBAL
        ));
    }

    private function cubre(object $regla, int $idVendedor): bool
    {
        $alcance = (string) ($regla->alcance ?? ComisionRegla::ALCANCE_GLOBAL);
        if ($alcance === ComisionRegla::ALCANCE_GLOBAL) {
            return true;
        }
        $ids = array_map('intval', (array) ($regla->id_vendedores ?? []));

        return in_array($idVendedor, $ids, true);
    }
}
