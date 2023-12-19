<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recibo extends Model
{
    use HasFactory;
    protected $table = 'abonos_ventas';
    protected $fillable = [
        'fecha',
        'concepto',
        'nombre_de',
        'monto',
        'forma_pago',
        'estado',
        'id_empresa',
        'id_venta',
    ];

    protected $appends = ['id_canal', 'id_documento', 'detalle_banco'];

    public function getIdCanalAttribute(){
        return $this->venta()->pluck('id_canal')->first();
    }

    public function getIdDocumentoAttribute(){
        return $this->venta()->pluck('id_documento')->first();
    }

    public function getDetalleBancoAttribute(){
        return $this->venta()->pluck('detalle_banco')->first();
    }

    public function venta(){
        return $this->belongsTo('App\Models\Ventas\Venta', 'id_venta');
    }

    public function GetVentaAttribute(){

        return $this->venta()->pluck('correlativo')->first();
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }
}
