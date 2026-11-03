<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

trait AuthorizesPartidaContable
{
    protected function authorizeGenerarPartida(Request $request, string $tipo): void
    {
        $user = $request->user();
        if (! $user) {
            throw new AccessDeniedHttpException();
        }

        $especifico = config("permissions.PERMISSION_CONTABILIDAD.partidas.generar_{$tipo}");
        $generico = config('permissions.PERMISSION_CONTABILIDAD.partidas.crear');

        if ($especifico && $user->can($especifico)) {
            return;
        }
        if ($generico && $user->can($generico)) {
            return;
        }

        throw new AccessDeniedHttpException('No tiene permiso para generar esta partida contable.');
    }
}
