<?php

namespace App\Models\Empleados\Empleados;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Comision extends Model {

    protected $table = 'empleado_comisiones';
    protected $fillable = array(
        'fecha',
        'concepto',
        'estado',
        'tipo',
        'nota',
        'total',
        'venta_id',
        'empleado_id',
        'usuario_id'
    );

    protected $appends = ['nombre_empleado'];

    public function getNombreEmpleadoAttribute()
    {
        return $this->empleado()->pluck('nombre')->first();
    }

    public function empleado(){
        return $this->belongsTo('App\Models\Empleados\Empleados\Empleado', 'empleado_id');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User', 'usuario_id');
    }

    public function venta(){
        return $this->belongsTo('App\Models\Ventas\Venta', 'venta_id');
    }


}