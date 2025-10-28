<?php

namespace App\Http\Resources\External;

use Illuminate\Http\Resources\Json\JsonResource;

class ReturnResource extends JsonResource
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
            'fecha' => $this->fecha,
            'correlativo' => $this->correlativo,
            'tipo' => $this->tipo,
            'sub_total' => (float) $this->sub_total,
            'no_sujeta' => (float) $this->no_sujeta,
            'exenta' => (float) $this->exenta,
            'cuenta_a_terceros' => (float) $this->cuenta_a_terceros,
            'total' => (float) $this->total,
            'iva' => (float) $this->iva,
            'iva_retenido' => (float) $this->iva_retenido,
            'observaciones' => $this->observaciones,
            'estado' => ($this->enable == '1') ? 'Confirmada' : 'Anulada',
            'id_venta' => $this->id_venta,
            
            // Campos relacionados (nombres)
            'nombre_cliente' => $this->nombre_cliente,
            'nombre_usuario' => $this->nombre_usuario,
            'nombre_documento' => $this->nombre_documento,
            
            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Detalles de la devolución
            'detalles' => ReturnDetailResource::collection($this->whenLoaded('detalles')),
        ];
    }
}
