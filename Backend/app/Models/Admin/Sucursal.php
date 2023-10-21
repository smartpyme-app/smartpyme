<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Sucursal extends Model {

    protected $table = 'sucursales';
    protected $fillable = [
        'nombre',
        'telefono',
        'correo',
        'direccion',
        'municipio',
        'departamento',
        'direccion',
        'id_empresa',
    ];

    public function cajas(){
        return $this->hasMany('App\Models\Admin\Caja', 'id_sucursal');
    }

    public function usuarios(){
        return $this->hasMany('App\Models\User', 'id_sucursal');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }


}
