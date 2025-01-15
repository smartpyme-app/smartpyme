<?php
namespace App\Services\Suscripcion;

use App\Models\Suscripcion;

class SuscripcionService
{
    public function createSuscripcion(array $suscripcionData): array
    {
        $suscripcion = Suscripcion::create([
            'empresa_id' => $suscripcionData['empresa_id'],
            'plan_id' => $suscripcionData['plan_id'], 
            'usuario_id' => $suscripcionData['usuario_id'],
            'tipo_plan' => $suscripcionData['tipo_plan'],
            'estado' => $suscripcionData['estado'],
            'monto' => $suscripcionData['monto'],
            'id_pago' => $suscripcionData['id_pago'] ?? null,
            'id_orden' => $suscripcionData['id_orden'] ?? null,
            'fecha_ultimo_pago' => $suscripcionData['fecha_ultimo_pago'] ?? null,
            'fecha_proximo_pago' => $suscripcionData['fecha_proximo_pago'] ?? null,
            'fin_periodo_prueba' => $suscripcionData['fin_periodo_prueba'] ?? null,
            'fecha_cancelacion' => $suscripcionData['fecha_cancelacion'] ?? null,
            'motivo_cancelacion' => $suscripcionData['motivo_cancelacion'] ?? null,
            'requiere_factura' => $suscripcionData['requiere_factura'] ?? false,
            'nit' => $suscripcionData['nit'] ?? null,
            'nombre_factura' => $suscripcionData['nombre_factura'] ?? null,
            'direccion_factura' => $suscripcionData['direccion_factura'] ?? null,
            'intentos_cobro' => $suscripcionData['intentos_cobro'] ?? 0,
            'ultimo_intento_cobro' => $suscripcionData['ultimo_intento_cobro'] ?? null,
            'historial_pagos' => $suscripcionData['historial_pagos'] ?? null
        ]);
        
        return $suscripcion->toArray();
    }
}