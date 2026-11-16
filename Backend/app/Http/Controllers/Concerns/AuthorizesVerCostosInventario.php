<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

trait AuthorizesVerCostosInventario
{
    protected function userCanVerCostosInventario(?Authenticatable $user): bool
    {
        if (! $user) {
            return false;
        }

        $perm = config('permissions.PERMISSION_PRODUCTOS.inventario.ver_costos');

        return $perm && $user->can($perm);
    }

    /** Ver costos en ficha de producto: ver_costos, ver inventario, editar productos o admin. */
    protected function userCanVerCostosEnFichaProducto(?Authenticatable $user): bool
    {
        if ($this->userCanVerCostosInventario($user)) {
            return true;
        }

        if (! $user) {
            return false;
        }

        $invVer = config('permissions.PERMISSION_PRODUCTOS.inventario.ver');
        if ($invVer && $user->can($invVer)) {
            return true;
        }

        $editar = config('permissions.PERMISSION_PRODUCTOS.editar');
        if ($editar && $user->can($editar)) {
            return true;
        }

        $tipo = $user->tipo ?? null;
        if (in_array($tipo, ['Administrador', 'Super Administrador'], true)) {
            return true;
        }

        foreach ([config('constants.ROL_ADMIN'), config('constants.ROL_SUPER_ADMIN')] as $rol) {
            if ($rol && method_exists($user, 'hasRole') && $user->hasRole($rol)) {
                return true;
            }
        }

        return false;
    }

    /** Costo/precio en flujo de compras (incluye compras.editar_precio para supervisor limitado). */
    protected function userCanEditarPrecioCompra(?Authenticatable $user): bool
    {
        if (! $user) {
            return false;
        }

        $editarPrecio = config('permissions.PERMISSION_COMPRAS.editar_precio');
        if ($editarPrecio && $user->can($editarPrecio)) {
            return true;
        }

        if ($user->hasRole(config('constants.ROL_SUPERVISOR_LIMITADO', 'supervisor_limitado'))) {
            return false;
        }

        return $this->userCanVerCostosInventario($user);
    }

    protected function authorizeVerCostosInventario(?Authenticatable $user): void
    {
        if (! $this->userCanVerCostosInventario($user)) {
            throw new AccessDeniedHttpException('No tiene permiso para ver costos de inventario.');
        }
    }

    /** @param object $producto Modelo Eloquent Producto */
    protected function ocultarCostosProductoSiAplica(object $producto): void
    {
        if ($this->userCanVerCostosEnFichaProducto(auth()->user())) {
            return;
        }

        foreach (['costo', 'costo_promedio', 'costo_anterior'] as $campo) {
            if (isset($producto->{$campo})) {
                $producto->{$campo} = null;
            }
        }
    }
}
