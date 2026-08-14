<?php

namespace App\Http\Resources\Restaurante;

use App\Models\Restaurante\Mesa;

/**
 * Payload liviano de GET /mesas para el mapa.
 * Mantiene claves snake_case que consume el frontend Angular.
 */
final class MesaMapaDto
{
    public static function fromModel(Mesa $mesa): array
    {
        $sesion = $mesa->sesionActiva;
        $zona = $mesa->zonaRestaurante;
        $reservas = $mesa->reservasActivas ?? collect();

        return [
            'id' => (int) $mesa->id,
            'numero' => (string) $mesa->numero,
            'capacidad' => (int) ($mesa->capacidad ?? 0),
            'zona_id' => $mesa->zona_id !== null ? (int) $mesa->zona_id : null,
            'zona' => $mesa->zona,
            'estado' => (string) $mesa->estado,
            'activo' => (bool) $mesa->activo,
            'orden' => (int) ($mesa->orden ?? 0),
            'id_sucursal' => $mesa->id_sucursal !== null ? (int) $mesa->id_sucursal : null,
            'tiempo_abierta' => $mesa->tiempo_abierta ?? null,
            'sesion_activa' => $sesion ? [
                'id' => (int) $sesion->id,
                'mesa_id' => (int) $sesion->mesa_id,
                'estado' => (string) $sesion->estado,
                'opened_at' => $sesion->opened_at?->toIso8601String(),
                'num_comensales' => (int) ($sesion->num_comensales ?? 1),
            ] : null,
            'zona_restaurante' => $zona ? [
                'id' => (int) $zona->id,
                'nombre' => (string) $zona->nombre,
                'orden' => (int) ($zona->orden ?? 0),
                'activo' => (bool) $zona->activo,
            ] : null,
            'reservas_activas' => $reservas->map(static fn ($r) => [
                'id' => (int) $r->id,
                'mesa_id' => (int) $r->mesa_id,
                'fecha_reserva' => $r->fecha_reserva?->toDateString(),
                'hora_reserva' => $r->hora_reserva,
                'cliente_nombre' => $r->cliente_nombre,
                'estado' => (string) $r->estado,
            ])->values()->all(),
        ];
    }
}
