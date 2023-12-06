<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class Cupon extends Model {

    protected $table = 'codigos';
    protected $fillable = array(
        'descripcion',
        'codigo',
        'total',
        'tipo',
        'estado',
        'id_empresa'
    );


    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }


}
