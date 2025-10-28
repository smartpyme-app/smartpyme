<?php

namespace App\Http\Resources\External;

use Illuminate\Http\Resources\Json\JsonResource;

class ReturnDetailResource extends JsonResource
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
            'nombre_producto' => $this->nombre_producto,
            'codigo_producto' => $this->codigo,
            'marca_producto' => $this->marca,
            'descripcion' => $this->descripcion,
            'cantidad' => (float) $this->cantidad,
            'precio' => (float) $this->precio,
            'costo' => (float) $this->costo,
            'descuento' => (float) $this->descuento,
            'no_sujeta' => (float) $this->no_sujeta,
            'cuenta_a_terceros' => (float) $this->cuenta_a_terceros,
            'exenta' => (float) $this->exenta,
            'total' => (float) $this->total,
            'medida' => $this->medida,
        ];
    }
}
