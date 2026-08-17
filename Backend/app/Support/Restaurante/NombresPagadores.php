<?php

namespace App\Support\Restaurante;

final class NombresPagadores
{
    public static function normalizar(?array $nombres, int $n): array
    {
        $n = max(0, $n);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $raw = isset($nombres[$i]) ? trim((string) $nombres[$i]) : '';
            if (mb_strlen($raw) > 80) {
                $raw = mb_substr($raw, 0, 80);
            }
            $out[] = $raw === '' ? ('Persona '.($i + 1)) : $raw;
        }

        return $out;
    }
}
