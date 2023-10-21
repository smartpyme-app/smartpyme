<?php

namespace App\Models\Contabilidad\CajaChica;

use Illuminate\Database\Eloquent\Model;

class CajaChica extends Model {

    protected $table = 'empresa_sucursal_cajas_chicas';
    protected $fillable = array(
        'apertura',
        'cierre',
        'descripcion',
        'entradas',
        'salidas',
        'saldo',
        'usuario_id',
        'sucursal_id',
    );

    protected $appends = ['nombre_usuario', 'nombre_sucursal'];

    public function getNombreUsuarioAttribute(){
        return $this->usuario()->pluck('name')->first();
    }

    public function getNombreSucursalAttribute(){
        return $this->sucursal()->pluck('nombre')->first();
    }

    public function detalles(){
        return $this->hasMany('App\Models\Contabilidad\CajaChica\Detalle', 'caja_id');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User', 'usuario_id');
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal', 'sucursal_id');
    }


}



