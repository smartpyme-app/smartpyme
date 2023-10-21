<?php

namespace App\Models\Empleados\Empleados;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Meta extends Model {

    protected $table = 'empleados_metas';
    protected $fillable = array(
        'mes',
        'ano',
        'meta',
        'tipo_comision',
        'comision',
        'nota',
        'empleado_id',
    );

    protected $appends = ['nombre_empleado', 'venta'];

    public function getNombreEmpleadoAttribute()
    {
        return $this->empleado()->pluck('nombre')->first();
    }

    public function getVentaAttribute(){
        return $this->empleado->ventas()
                        ->where('estado', 'Pagada')
                        ->whereYear('fecha', $this->ano)
                        ->whereMonth('fecha', $this->mes)
                        ->sum('total');

    }


    public function empleado(){
        return $this->belongsTo('App\Models\Empleados\Empleados\Empleado', 'empleado_id');
    }


}