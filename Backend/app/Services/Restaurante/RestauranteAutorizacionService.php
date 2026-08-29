<?php

namespace App\Services\Restaurante;

use App\Models\User;
use App\Services\Admin\UsuarioService;

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

    /** SP-2158: cerrar mesa sin factura (Supervisor, Administrador, Ventas). */
    public function usuarioPuedeCerrarMesa(?User $user): bool
    {
        if (! $user || empty($user->tipo)) {
            return false;
        }

        return in_array(trim((string) $user->tipo), ['Administrador', 'Supervisor', 'Ventas'], true);
    }

    /** Cierre con consumo/pre-cuenta pendiente: Admin o Supervisor sin código extra. */
    public function usuarioPuedeCerrarMesaForzadaSinCodigo(?User $user): bool
    {
        if (! $user || empty($user->tipo)) {
            return false;
        }

        return in_array(trim((string) $user->tipo), ['Administrador', 'Supervisor'], true);
    }

    public function usuarioEsVentas(?User $user): bool
    {
        return $user && trim((string) $user->tipo) === 'Ventas';
    }

    /** Valida PIN de supervisor (campo users.codigo) de la misma empresa. */
    public function supervisorAutorizaCierreForzado(?User $actor, string $codigo): ?User
    {
        if (! $actor || trim($codigo) === '') {
            return null;
        }

        $supervisor = app(UsuarioService::class)->validarCodigoSupervisor(trim($codigo));
        if (! $supervisor) {
            return null;
        }
        if ((int) $supervisor->id_empresa !== (int) $actor->id_empresa) {
            return null;
        }
        if (! in_array(trim((string) $supervisor->tipo), ['Administrador', 'Supervisor'], true)) {
            return null;
        }

        return $supervisor;
    }
}
