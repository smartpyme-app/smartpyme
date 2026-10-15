<?php

namespace App\Services\Contadores;

use App\Models\Admin\Sucursal;
use App\Models\Inventario\Bodega;
use App\Models\User;

/**
 * En el portal, users.id_empresa pasa a la PYME que el contador está viendo.
 * id_empresa_firma_contador conserva la empresa propia para App y Unificado.
 */
class ContadorSesionEmpresa
{
    public static function entradaDesdePortalContadores(?string $origin): bool
    {
        return str_contains(strtolower((string) $origin), 'contadores');
    }

    /** App y unificado vuelven a la empresa firma. El portal no toca el contexto. */
    public function aplicarEmpresaDeEntrada(User $user, ?string $origin): void
    {
        if (self::entradaDesdePortalContadores($origin)) {
            return;
        }

        $firma = (int) $user->id_empresa_firma_contador;
        if (!$firma || $firma === (int) $user->id_empresa) {
            return;
        }

        $idSucursal = Sucursal::withoutGlobalScopes()
            ->where('id_empresa', $firma)
            ->orderBy('id')
            ->value('id');
        $idBodega = $idSucursal
            ? Bodega::withoutGlobalScopes()->where('id_sucursal', $idSucursal)->orderBy('id')->value('id')
            : null;

        if (!$idSucursal || !$idBodega) {
            return;
        }

        User::withoutGlobalScopes()
            ->whereKey($user->id)
            ->update([
                'id_empresa' => $firma,
                'id_sucursal' => $idSucursal,
                'id_bodega' => $idBodega,
            ]);

        $user->id_empresa = $firma;
        $user->id_sucursal = $idSucursal;
        $user->id_bodega = $idBodega;
        $user->syncOriginal();
    }
}
