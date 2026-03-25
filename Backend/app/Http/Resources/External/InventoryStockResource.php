<?php

namespace App\Http\Resources\External;

use Illuminate\Http\Resources\Json\JsonResource;

class InventoryStockResource extends JsonResource
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
            'id_producto' => $this->id_producto,
            'stock' => $this->stock,
            'stock_minimo' => $this->stock_minimo,
            'stock_maximo' => $this->stock_maximo,
            'nota' => $this->nota,            
            'nombre_bodega' => $this->nombre_bodega,
            'nombre_sucursal' => $this->nombre_sucursal,
        ];
    }
}


