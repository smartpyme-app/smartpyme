<?php

namespace App\Services\Contabilidad\Partidas;

class ReglaAbonosCartera
{
    public static function incluirEnIngresosEgresos(object $config): bool
    {
        return empty($config->abonos_en_cartera);
    }
}
