<?php

namespace App\Support;

use App\Models\Admin\Empresa;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Alcance de usuarios admin org: licencia del usuario en sesión; super_admin sin límite. */
final class AdminUsuariosAlcance
{
    /** @return int[]|null null = super_admin (sin whereIn) */
    public static function idsEmpresasPermitidas(User $actor): ?array
    {
        if ($actor->hasRole('super_admin')) {
            return null;
        }

        $empresa = Empresa::find($actor->id_empresa);
        if (!$empresa) {
            return [(int) $actor->id_empresa];
        }

        $ids = array_map('intval', $empresa->getEmpresasLicenciaIds());

        return $ids !== [] ? $ids : [(int) $actor->id_empresa];
    }

    public static function puedeGestionarUsuario(User $actor, User $target): bool
    {
        $permitidos = self::idsEmpresasPermitidas($actor);
        if ($permitidos === null) {
            return true;
        }

        return in_array((int) $target->id_empresa, $permitidos, true);
    }

    public static function empresaIdPermitido(User $actor, int $idEmpresa): bool
    {
        $permitidos = self::idsEmpresasPermitidas($actor);
        if ($permitidos === null) {
            return true;
        }

        return in_array($idEmpresa, $permitidos, true);
    }

    public static function aplicarAlQuery(Builder $query, User $actor, Request $request): Builder
    {
        $permitidos = self::idsEmpresasPermitidas($actor);
        if ($permitidos !== null) {
            $query->whereIn('id_empresa', $permitidos);
        }

        if ($request->filled('id_empresa')) {
            $idEmpresa = (int) $request->id_empresa;
            if (!self::empresaIdPermitido($actor, $idEmpresa)) {
                return $query->whereRaw('1 = 0');
            }
            $query->where('id_empresa', $idEmpresa);
        }

        return $query;
    }

    public static function aplicarBuscador(Builder $query, ?string $buscador): Builder
    {
        if ($buscador === null || $buscador === '') {
            return $query;
        }

        return $query->where(function ($q) use ($buscador) {
            $q->where('name', 'like', '%' . $buscador . '%')
                ->orWhere('email', 'like', '%' . $buscador . '%');
        });
    }
}
