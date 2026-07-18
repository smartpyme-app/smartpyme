<?php

namespace App\Services\Admin;

use App\Models\Admin\Caja;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class CajaUsuarioResolver
{
    /**
     * Resuelve la caja del usuario: por caja_id, por sucursal o creando una principal.
     */
    public function resolverParaUsuario(User $usuario): ?Caja
    {
        if (! Schema::hasTable('cajas')) {
            return null;
        }

        $cajaId = $usuario->caja_id ?? null;
        if ($cajaId) {
            $caja = Caja::find($cajaId);
            if ($caja) {
                return $caja;
            }
        }

        $sucursalId = $usuario->id_sucursal ?? $usuario->sucursal_id ?? null;
        if (! $sucursalId) {
            return null;
        }

        $cajaSucursal = Caja::where('sucursal_id', $sucursalId)->first();
        if ($cajaSucursal) {
            return $cajaSucursal;
        }

        return Caja::create([
            'nombre' => 'Principal',
            'tipo' => 'Punto de venta',
            'descripcion' => 'Caja creada automáticamente para la sucursal',
            'sucursal_id' => $sucursalId,
        ]);
    }
}
