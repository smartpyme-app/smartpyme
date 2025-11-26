<?php

namespace App\Http\Resources\Planilla;

use Illuminate\Http\Resources\Json\JsonResource;

class PlanillaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'fecha_inicio' => $this->fecha_inicio,
            'fecha_fin' => $this->fecha_fin,
            'tipo_planilla' => $this->tipo_planilla,
            'estado' => $this->estado,
            'total_salarios' => $this->total_salarios,
            'total_deducciones' => $this->total_deducciones,
            'total_neto' => $this->total_neto,
            'total_aportes_patronales' => $this->total_aportes_patronales,
            'anio' => $this->anio,
            'mes' => $this->mes,
            'id_empresa' => $this->id_empresa,
            'id_sucursal' => $this->id_sucursal,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'detalles' => PlanillaDetalleResource::collection($this->whenLoaded('detalles')),
            'empresa' => $this->whenLoaded('empresa'),
            'sucursal' => $this->whenLoaded('sucursal')
        ];
    }
}

