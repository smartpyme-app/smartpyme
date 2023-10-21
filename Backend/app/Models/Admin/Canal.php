<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Canal extends Model {

    protected $table = 'canales';
    protected $fillable = array(
        'nombre',
        'id_empresa'
    );

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa','id_empresa');
    }

    public function ventas(){
        return $this->hasMany('App\Models\Ventas\Venta','canal_id');
    }



}
