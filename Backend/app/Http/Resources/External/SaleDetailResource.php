<?php

namespace App\Http\Resources\External;

use Illuminate\Http\Resources\Json\JsonResource;

class SaleDetailResource extends JsonResource
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
            // Campos del detalle (todos excepto id)
            //'id_producto' => $this->id_producto,
            'nombre_producto' => $this->nombre_producto,
            'codigo_producto' => $this->codigo,
            'marca_producto' => $this->marca,
            'cantidad' => $this->cantidad,
            'precio' => $this->precio,
            'costo' => $this->costo,
            'descuento' => $this->descuento,
            //'no_sujeta' => $this->no_sujeta,
            //'exenta' => $this->exenta,
            //'gravada' => $this->gravada,
            //'cuenta_a_terceros' => $this->cuenta_a_terceros,
            'total_costo' => $this->total_costo,
            'total' => $this->total,
            //'id_venta' => $this->id_venta,
            //'id_vendedor' => $this->id_vendedor,
            'iva' => $this->iva,
        ];
    }
}

