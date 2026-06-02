<?php

namespace App\Services\Dte;

/**
 * Utilidades para leer campos comunes en JSON DTE de distintos proveedores.
 */
class DteJsonHelper
{
    /**
     * Extrae el sello de recepción del MH desde formatos habituales.
     */
    public static function extractSelloRecibido(array $jsonData): ?string
    {
        $candidates = [
            $jsonData['selloRecibido'] ?? null,
            $jsonData['sello'] ?? null,
            $jsonData['responseMH']['selloRecibido'] ?? null,
            $jsonData['documento']['selloRecibido'] ?? null,
        ];

        foreach ($candidates as $value) {
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }
}
