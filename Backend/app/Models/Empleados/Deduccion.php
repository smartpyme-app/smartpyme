<?php

namespace App\Models\Empleados;

use Illuminate\Database\Eloquent\Model;

class Deduccion extends Model {

    protected $table = 'empresa_deducciones';
    protected $fillable = array(
        'nombre',
        'tipo',
        'descripcion',
        'total',
        'empresa_id',
    );

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'empresa_id');
    }


}



