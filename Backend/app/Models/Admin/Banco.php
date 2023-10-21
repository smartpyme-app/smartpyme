<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Banco extends Model {

    protected $table = 'empresa_bancos';
    protected $fillable = array(
        'nombre',
        'direccion',
        'contacto',
        'empresa_id'

    );

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'empresa_id');
    }


}
