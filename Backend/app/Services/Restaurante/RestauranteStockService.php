<?php

namespace App\Services\Restaurante;

use App\Models\Admin\Empresa;
use App\Models\Inventario\Bodega;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\Restaurante\SesionMesa;
use App\Models\User;

class RestauranteStockService
{
    /**
     * Bodega de referencia para alertar stock en mesa (misma lógica que facturación: inventario por bodega).
     */
    public function resolverIdBodega(SesionMesa $sesion, User $user): ?int
    {
        if (!empty($user->id_bodega)) {
            return (int) $user->id_bodega;
        }
        if (!empty($sesion->id_sucursal)) {
            $b = Bodega::where('id_sucursal', $sesion->id_sucursal)
                ->where('activo', '1')
                ->orderBy('id')
                ->first();
            if ($b) {
                return (int) $b->id;
            }
        }
        $b = Bodega::where('id_empresa', $user->id_empresa)->where('activo', '1')->orderBy('id')->first();

        return $b ? (int) $b->id : null;
    }

    /**
     * @return array{ok: bool, disponible: ?float, mensaje: ?string}
     */
    public function validarDisponibilidad(
        Producto $producto,
        int $idBodega,
        float $cantidadSolicitada,
        Empresa $empresa
    ): array {
        if ($producto->tipo === 'Servicio') {
            return ['ok' => true, 'disponible' => null, 'mensaje' => null];
        }

        $puedeSinStock = (int) ($empresa->vender_sin_stock ?? 0) === 1;
        if ($puedeSinStock) {
            return ['ok' => true, 'disponible' => null, 'mensaje' => null];
        }

        $inv = Inventario::where('id_producto', $producto->id)->where('id_bodega', $idBodega)->first();
        if (!$inv) {
            return [
                'ok' => false,
                'disponible' => 0,
                'mensaje' => 'No hay registro de inventario para '.$producto->nombre.' en la bodega de venta.',
            ];
        }

        $disp = (float) $inv->stock;
        if ($disp < $cantidadSolicitada) {
            return [
                'ok' => false,
                'disponible' => $disp,
                'mensaje' => 'Stock insuficiente para '.$producto->nombre.'. Disponible: '.$disp.', solicitado: '.$cantidadSolicitada,
            ];
        }

        return ['ok' => true, 'disponible' => $disp, 'mensaje' => null];
    }
}
