<?php

namespace App\Models\Contabilidad\CajaChica;

use Illuminate\Database\Eloquent\Model;

class Detalle extends Model {

    protected $table = 'empresa_sucursal_caja_chica_detalles';
    protected $fillable = array(
        'fecha',
        'descripcion',
        'referencia',
        'tipo',
        'entrada',
        'salida',
        'saldo',
        'usuario_id',
        'caja_id',
    );

    protected $appends = ['nombre_usuario'];

    public function getNombreUsuarioAttribute(){
        return $this->usuario()->pluck('name')->first();
    }

    public function getNombreSucursalAttribute(){
        return $this->sucursal()->pluck('nombre')->first();
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User', 'usuario_id');
    }

    public function caja(){
        return $this->belongsTo('App\Models\Contabilidad\CajaChica\CajaChica', 'caja_id');
    }


}



