<?php

namespace App\Http\Resources\External;

use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
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
            //'tipo_dte' => $this->tipo_dte,
            //'numero_control' => $this->numero_control,
            //'codigo_generacion' => $this->codigo_generacion,
            //'sello_mh' => $this->sello_mh,
            //'prueba_masiva' => $this->prueba_masiva,
            'fecha' => $this->fecha,
            'correlativo' => $this->correlativo,
            //'num_identificacion' => $this->num_identificacion,
            'estado' => $this->estado,
            //'detalle_banco' => $this->detalle_banco,
            //'id_canal' => $this->id_canal,
            //'id_documento' => $this->id_documento,
            'forma_pago' => $this->forma_pago,
            //'tipo_documento' => $this->tipo_documento,
            //'num_cotizacion' => $this->num_cotizacion,
            //'num_orden' => $this->num_orden,
            //'num_orden_exento' => $this->num_orden_exento,
            //'condicion' => $this->condicion,
            //'referencia' => $this->referencia,
            //'fecha_pago' => $this->fecha_pago,
            //'fecha_expiracion' => $this->fecha_expiracion,
            'monto_pago' => $this->monto_pago,
            'cambio' => $this->cambio,
            'iva_percibido' => $this->iva_percibido,
            'iva_retenido' => $this->iva_retenido,
            'renta_retenida' => $this->renta_retenida,
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
            //'descripcion_personalizada' => $this->descripcion_personalizada,
            'descripcion_impresion' => $this->descripcion_impresion,
            'nombre_cliente' => $this->nombre_cliente,
            'nombre_usuario' => $this->nombre_usuario,
            'nombre_vendedor' => $this->nombre_vendedor,
            'nombre_sucursal' => $this->nombre_sucursal,
            'nombre_canal' => $this->nombre_canal,
            'nombre_documento' => $this->nombre_documento,
            // 'nombre_proyecto' => $this->nombre_proyecto, // Comentado por conflicto JWT
            'saldo' => $this->saldo,
            //'id_caja' => $this->id_caja,
            //'id_proyecto' => $this->id_proyecto,
            //'id_bodega' => $this->id_bodega,
            //'id_corte' => $this->id_corte,
            //'id_cliente' => $this->id_cliente,
            //'id_usuario' => $this->id_usuario,
            //'id_vendedor' => $this->id_vendedor,
            //'id_empresa' => $this->id_empresa,
            //'id_sucursal' => $this->id_sucursal,
            //'dte' => $this->dte,
           // 'dte_invalidacion' => $this->dte_invalidacion,
            //'tipo_item_export' => $this->tipo_item_export,
            //'cod_incoterm' => $this->cod_incoterm,
            //'incoterm' => $this->incoterm,
            //'recinto_fiscal' => $this->recinto_fiscal,
           //'regimen' => $this->regimen,
            //'seguro' => $this->seguro,
            //'flete' => $this->flete,
            //'tipo_operacion' => $this->tipo_operacion,
            //'tipo_renta' => $this->tipo_renta,
                        
            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Detalles de la venta
            'detalles' => SaleDetailResource::collection($this->whenLoaded('detalles')),
        ];
    }
}
