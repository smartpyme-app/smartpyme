<?php

namespace App\Http\Resources\Ventas;

use Illuminate\Http\Resources\Json\JsonResource;

class DetalleVentaResource extends JsonResource
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
            'id_venta' => $this->id_venta,
            'id_producto' => $this->id_producto,
            'cantidad' => $this->cantidad,
            'precio' => $this->precio,
            'descuento' => $this->descuento,
            'total' => $this->total,
            'id_vendedor' => $this->id_vendedor,
            'producto' => new ProductoResource($this->whenLoaded('producto')),
            'vendedor' => new VendedorResource($this->whenLoaded('vendedor')),
            'composiciones' => ComposicionResource::collection($this->whenLoaded('composiciones')),
        ];
    }
}


