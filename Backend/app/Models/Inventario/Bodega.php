<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class Bodega extends Model {

    protected $table = 'empresa_sucursal_bodegas';
    protected $fillable = array(
        'nombre',
        'descripcion',
        'sucursal_id'
    );

    protected $appends = ['nombre_sucursal'];

    public function getNombreSucursalAttribute()
    {
        return $this->sucursal()->pluck('nombre')->first();
    }

    public function productos(){
        return $this->hasMany('App\Models\Inventario\Inventario', 'bodega_id');
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal', 'sucursal_id');
    }


}



