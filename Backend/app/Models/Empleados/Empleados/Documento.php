<?php

namespace App\Models\Empleados\Empleados;

use Illuminate\Database\Eloquent\Model;

class Documento extends Model {

    protected $table = 'empleado_documentos';
    protected $fillable = array(
        'nombre',
        'url',
        'tamano',
        'tipo',
        'empleado_id'
    );


    public function empleado(){
        return $this->belongsTo('App\Models\Empleados\Empleados\Empleado', 'empleado_id');
    }


}



