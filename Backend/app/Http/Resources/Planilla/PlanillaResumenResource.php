<?php

namespace App\Http\Resources\Planilla;

use Illuminate\Http\Resources\Json\JsonResource;

class PlanillaResumenResource extends JsonResource
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
            'cantidad_empleados' => $this->when(isset($this->detalles_count), $this->detalles_count),
            'empresa' => $this->whenLoaded('empresa', function () {
                return [
                    'id' => $this->empresa->id,
                    'nombre' => $this->empresa->nombre,
                    'cod_pais' => $this->empresa->cod_pais
                ];
            })
        ];
    }
}

