<?php

namespace App\Services\Clinica;

class PacienteReglas
{
    public const SEXOS_HUMANO = ['masculino', 'femenino', 'otro'];

    public const SEXOS_ANIMAL = ['macho', 'hembra', 'desconocido'];

    public static function vacio(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    public static function razaObligatoria(int $cantidadRazas): bool
    {
        return $cantidadRazas > 0;
    }
}
