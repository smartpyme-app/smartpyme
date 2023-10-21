<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Model;

class Presupuesto extends Model {

    protected $table = 'empresa_wompi';
    protected $fillable = array(
        'nombre',
        'inicio',
        'fin',
        'ingresos',
        'egresos',
        'alquiler',
        'varios',
        'mantenimiento',
        'marketing',
        'materia_prima',
        'comisiones',
        'combustible',
        'planilla',
        'servicios',
        'prestamos',
        'publicidad',
        'empresa_id',
    );

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'empresa_id');
    }


}
