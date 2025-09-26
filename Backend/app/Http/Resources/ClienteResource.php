<?php

namespace App\Http\Resources;

use App\Services\FidelizacionCliente\PuntosService;
use Illuminate\Http\Resources\Json\JsonResource;

class ClienteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        $puntosCliente = $this->puntosCliente;
        $ultimaVenta = $this->ventas->first();
        $tipoCliente = $this->tipoCliente;

        return [
            'id' => $this->id,
            'nombre' => $this->tipo === 'Empresa' ? $this->nombre_empresa : $this->nombre_completo,
            'correo' => $this->correo,
            'telefono' => $this->telefono,
            'dui' => $this->dui,
            'ncr' => $this->ncr,
            'tipo' => $this->tipo,
            'enable' => $this->enable,
            'tipo_cliente_fidelizacion' => $this->when($tipoCliente, [
                'id' => $tipoCliente?->id,
                'nivel' => $tipoCliente?->nivel,
                'nombre_efectivo' => $tipoCliente?->nombre_efectivo,
                'descripcion_efectiva' => $tipoCliente?->descripcion_efectiva,
                'puntos_por_dolar' => $tipoCliente?->puntos_por_dolar,
                'minimo_canje' => $tipoCliente?->minimo_canje,
                'maximo_canje' => $tipoCliente?->maximo_canje,
                'expiracion_meses' => $tipoCliente?->expiracion_meses,
            ]),
            'puntos_acumulados' => $puntosCliente?->puntos_totales_ganados ?? 0,
            'puntos_disponibles' => $puntosCliente?->puntos_disponibles ?? 0,
            'puntos_vencidos' => $this->calcularPuntosVencidos($this->id),
            'ultima_compra' => $ultimaVenta?->created_at?->format('Y-m-d'),
            'total_compras' => $this->ventas()->count(),
            'total_gastado' => $this->ventas()->sum('total'),
            'nivel_actual' => $tipoCliente?->nivel ?? 1,
            'fecha_registro' => $this->created_at->format('Y-m-d'),
            'fecha_ultima_actividad' => $puntosCliente?->fecha_ultima_actividad,
        ];
    }

    /**
     * Calcula puntos vencidos para el cliente
     */
    private function calcularPuntosVencidos(int $clienteId): int
    {
        // Implementar lógica de cálculo de puntos vencidos
        // Esta lógica debería estar en un servicio separado
        return app(PuntosService::class)->calcularPuntosVencidos($clienteId);
    }
}