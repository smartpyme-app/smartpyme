<?php

namespace App\Services\Restaurante;

use App\Models\User;

class RestauranteAutorizacionService
{
    /**
     * Perfil con permiso para anular ítems ya enviados o trasladar entre mesas.
     */
    public function usuarioPuedeAutorizarOperaciones(?User $user): bool
    {
        if (!$user || empty($user->tipo)) {
            return false;
        }
        $t = mb_strtolower(trim((string) $user->tipo));

        return in_array($t, ['administrador', 'admin', 'gerente'], true);
    }
}
