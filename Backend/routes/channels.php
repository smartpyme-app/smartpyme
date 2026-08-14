<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Canal privado por tenant. Solo usuarios de la misma empresa.
 * Payload/eventos son hints de UI; la SoT sigue siendo MariaDB vía HTTP GET.
 */
Broadcast::channel('restaurante.empresa.{idEmpresa}', function ($user, int $idEmpresa) {
    if (! $user || ! $user->id_empresa) {
        return false;
    }

    return (int) $user->id_empresa === (int) $idEmpresa;
});
