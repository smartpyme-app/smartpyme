<?php

namespace App\Models\Empleados;

use Illuminate\Database\Eloquent\Model;

class Cargo extends Model {

    protected $table = 'empleados_cargos';
    protected $fillable = array(
        'nombre',
        'empresa_id',
    );

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'empresa_id');
    }


}



