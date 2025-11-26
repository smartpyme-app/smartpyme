<?php

namespace App\Http\Resources\Planilla;

use Illuminate\Http\Resources\Json\JsonResource;

class PlanillaDetalleResource extends JsonResource
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
            'id_planilla' => $this->id_planilla,
            'id_empleado' => $this->id_empleado,
            'salario_base' => $this->salario_base,
            'salario_devengado' => $this->salario_devengado,
            'dias_laborados' => $this->dias_laborados,
            'horas_extra' => $this->horas_extra,
            'monto_horas_extra' => $this->monto_horas_extra,
            'comisiones' => $this->comisiones,
            'bonificaciones' => $this->bonificaciones,
            'otros_ingresos' => $this->otros_ingresos,
            'isss_empleado' => $this->isss_empleado,
            'isss_patronal' => $this->isss_patronal,
            'afp_empleado' => $this->afp_empleado,
            'afp_patronal' => $this->afp_patronal,
            'renta' => $this->renta,
            'prestamos' => $this->prestamos,
            'anticipos' => $this->anticipos,
            'otros_descuentos' => $this->otros_descuentos,
            'descuentos_judiciales' => $this->descuentos_judiciales,
            'detalle_otras_deducciones' => $this->detalle_otras_deducciones,
            'total_ingresos' => $this->total_ingresos,
            'total_descuentos' => $this->total_descuentos,
            'sueldo_neto' => $this->sueldo_neto,
            'estado' => $this->estado,
            'conceptos_personalizados' => $this->conceptos_personalizados,
            'pais_configuracion' => $this->pais_configuracion,
            'empleado' => $this->whenLoaded('empleado'),
            'planilla' => $this->whenLoaded('planilla')
        ];
    }
}

