<?php

namespace App\Support\Eventos;

class AjusteFechasCita
{
    private const REPITE = ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'];

    public static function aplicar(array $data): array
    {
        $frecuencia = $data['frecuencia'] ?? null;
        if (!in_array($frecuencia, self::REPITE, true)) {
            $data['frecuencia'] = null;
            $data['frecuencia_fin'] = null;
        }

        $inicio = strtotime((string) ($data['inicio'] ?? ''));
        $fin = strtotime((string) ($data['fin'] ?? ''));
        if ($inicio && $fin && $fin < $inicio && date('Y-m-d', $fin) === date('Y-m-d', $inicio)) {
            $data['fin'] = date('Y-m-d H:i:s', strtotime('+1 day', $fin));
        }

        return $data;
    }
}
