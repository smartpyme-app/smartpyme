<?php

namespace App\Services\Clinica;

class ExpedienteNumero
{
    public static function siguiente(int $ultimo): int
    {
        return $ultimo + 1;
    }
}
