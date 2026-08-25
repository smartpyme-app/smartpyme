<?php

namespace App\Services\CreditosClientes;

class CorrelativoVenta
{
    public static function debeAsignar($correlativoExistente): bool
    {
        if ($correlativoExistente === null || $correlativoExistente === '') {
            return true;
        }

        return (int) $correlativoExistente === 0;
    }
}
