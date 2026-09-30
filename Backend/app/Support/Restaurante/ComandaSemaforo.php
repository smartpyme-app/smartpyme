<?php

namespace App\Support\Restaurante;

use App\Models\Admin\Empresa;
use Carbon\CarbonInterface;

final class ComandaSemaforo
{
    public const VERDE_DEFAULT = 7;

    public const AMARILLO_DEFAULT = 14;

    public const CLAVE_VERDE = 'restaurante_semaforo_verde_min';

    public const CLAVE_AMARILLO = 'restaurante_semaforo_amarillo_min';

    /** Verde mientras no llegue al primer corte; amarillo hasta el segundo; después rojo. */
    public static function color(int $segundos, int $verdeMin, int $amarilloMin): string
    {
        $verde = max(1, $verdeMin) * 60;
        $amarillo = max($verdeMin + 1, $amarilloMin) * 60;
        if ($segundos < $verde) {
            return 'verde';
        }
        if ($segundos < $amarillo) {
            return 'amarillo';
        }

        return 'rojo';
    }

    public static function segundos(CarbonInterface $inicio, CarbonInterface $fin): int
    {
        return max(0, $fin->getTimestamp() - $inicio->getTimestamp());
    }

    /** @return array{verde_min: int, amarillo_min: int} */
    public static function leer(?Empresa $empresa): array
    {
        $verde = (int) ($empresa?->getCustomConfigValue('configuraciones', self::CLAVE_VERDE, self::VERDE_DEFAULT) ?? self::VERDE_DEFAULT);
        $amarillo = (int) ($empresa?->getCustomConfigValue('configuraciones', self::CLAVE_AMARILLO, self::AMARILLO_DEFAULT) ?? self::AMARILLO_DEFAULT);
        if ($verde < 1) {
            $verde = self::VERDE_DEFAULT;
        }
        if ($amarillo <= $verde) {
            $amarillo = $verde + self::AMARILLO_DEFAULT - self::VERDE_DEFAULT;
        }

        return ['verde_min' => $verde, 'amarillo_min' => $amarillo];
    }
}
