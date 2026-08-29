<?php

namespace App\Services\Restaurante;

use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\ItemEliminacionLog;
use App\Models\Restaurante\OrdenDetalle;
use Illuminate\Support\Facades\DB;

class SesionMesaForceCloseService
{
    /**
     * Anula consumo y pre-cuentas pendientes al cerrar mesa sin facturar (SP-2158).
     * ponytail: no genera comanda ELIMINADO por ítem; queda en ItemEliminacionLog.
     */
    public static function liquidar(int $sesionId, int $usuarioId, ?int $autorizadoUsuarioId = null): void
    {
        DB::transaction(function () use ($sesionId, $usuarioId, $autorizadoUsuarioId) {
            foreach (OrdenDetalle::where('sesion_id', $sesionId)->get() as $item) {
                ItemEliminacionLog::create([
                    'orden_detalle_id' => $item->id,
                    'sesion_id' => $sesionId,
                    'producto_id' => $item->producto_id,
                    'cantidad' => $item->cantidad,
                    'precio_unitario' => $item->precio_unitario,
                    'notas' => $item->notas,
                    'enviado_cocina' => $item->enviado_cocina,
                    'enviado_barra' => $item->enviado_barra,
                    'motivo_codigo' => 'cierre_mesa_forzado',
                    'motivo_detalle' => 'Cierre de mesa sin facturar',
                    'usuario_id' => $usuarioId,
                    'autorizado_usuario_id' => $autorizadoUsuarioId ?? $usuarioId,
                ]);
                $item->delete();
            }

            PreCuentaSesionCleanup::anularPendientes($sesionId);

            Comanda::where('sesion_id', $sesionId)
                ->whereIn('destino', ['cocina', 'barra', 'ambos'])
                ->whereIn('estado', ['pendiente', 'preparando', 'listo'])
                ->update(['estado' => 'servido']);
        });
    }
}
