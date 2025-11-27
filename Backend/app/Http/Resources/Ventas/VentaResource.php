<?php

namespace App\Http\Resources\Ventas;

use Illuminate\Http\Resources\Json\JsonResource;

class VentaResource extends JsonResource
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
            'num_identificacion' => $this->num_identificacion,
            'estado' => $this->estado,
            'forma_pago' => $this->forma_pago,
            'metodo_pago' => $this->metodo_pago,
            'iva' => $this->iva,
            'total_costo' => $this->total_costo,
            'descuento' => $this->descuento,
            'sub_total' => $this->sub_total,
            'no_sujeta' => $this->no_sujeta,
            'exenta' => $this->exenta,
            'gravada' => $this->gravada,
            'cuenta_a_terceros' => $this->cuenta_a_terceros,
            'total' => $this->total,
            'propina' => $this->propina,
            'observaciones' => $this->observaciones,
            'recurrente' => $this->recurrente,
            'cotizacion' => $this->cotizacion,
            'descripcion_impresion' => $this->descripcion_impresion,
            'nombre_cliente' => $this->nombre_cliente,
            'nombre_usuario' => $this->nombre_usuario,
            'nombre_vendedor' => $this->nombre_vendedor,
            'nombre_sucursal' => $this->nombre_sucursal,
            'nombre_canal' => $this->nombre_canal,
            'nombre_documento' => $this->nombre_documento,
            'saldo' => $this->saldo,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relaciones
            'cliente' => new ClienteResource($this->whenLoaded('cliente')),
            'detalles' => DetalleVentaResource::collection($this->whenLoaded('detalles')),
            'impuestos' => ImpuestoResource::collection($this->whenLoaded('impuestos')),
            'metodos_de_pago' => MetodoPagoResource::collection($this->whenLoaded('metodos_de_pago')),
            'abonos' => AbonoResource::collection($this->whenLoaded('abonos')),
            'devoluciones' => DevolucionResource::collection($this->whenLoaded('devoluciones')),
        ];
    }
}


