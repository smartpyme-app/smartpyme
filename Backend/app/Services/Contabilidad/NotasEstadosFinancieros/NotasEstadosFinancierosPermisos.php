<?php

namespace App\Services\Contabilidad\NotasEstadosFinancieros;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class NotasEstadosFinancierosPermisos
{
    /** @var list<string> */
    private const VER = [
        'super_admin', 'admin', 'usuario_contador', 'auxiliar_contable',
        'gerente_operaciones', 'gerente_compras', 'gerencia_general',
    ];

    /** @var list<string> */
    private const EDITAR_AUTO = ['super_admin', 'admin', 'usuario_contador'];

    /** @var list<string> */
    private const EDITAR_MANUAL = ['super_admin', 'admin', 'usuario_contador'];

    /** @var list<string> */
    private const EMITIR = ['super_admin', 'admin', 'gerente_operaciones', 'gerencia_general'];

    public static function puedeVer(?User $user = null): bool
    {
        return self::tieneAlguno($user, self::VER);
    }

    public static function puedeEditarAuto(?User $user = null): bool
    {
        return self::tieneAlguno($user, self::EDITAR_AUTO);
    }

    public static function puedeEditarManual(?User $user = null): bool
    {
        return self::tieneAlguno($user, self::EDITAR_MANUAL);
    }

    public static function puedeEmitir(?User $user = null): bool
    {
        return self::tieneAlguno($user, self::EMITIR);
    }

    /** @param  list<string>  $roles */
    private static function tieneAlguno(?User $user, array $roles): bool
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return false;
        }

        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }
}
