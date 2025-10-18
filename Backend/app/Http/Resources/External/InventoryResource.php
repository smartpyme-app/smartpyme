<?php

namespace App\Http\Resources\External;

use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
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
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'codigo' => $this->codigo,
            'barcode' => $this->barcode,
            'nombre_categoria' => $this->nombre_categoria,
            'precio' => $this->precio,
            'costo' => $this->costo,
            'costo_anterior' => $this->costo_anterior,
            'costo_promedio' => $this->costo_promedio,
            'marca' => $this->marca,
            'tipo' => $this->tipo,
            'enable' => $this->enable,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Inventarios por bodega
            'inventarios' => InventoryStockResource::collection($this->whenLoaded('inventarios')),
        ];
    }
}


