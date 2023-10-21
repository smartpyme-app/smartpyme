<?php

namespace App\Models\Empleados\Empleados;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Asistencia extends Model {

    protected $table = 'empleados_asistencias';
    protected $fillable = array(
        'empleado_id',
        'fecha',
        'entrada',
        'salida',
        'estado',
        'nota',
    );

    protected $appends = ['nombre_empleado', 'horas'];

    public function getHorasAttribute()
    {
        if ($this->entrada)
            if ($this->salida)
                return Carbon::parse($this->entrada)->diffInHours(Carbon::parse($this->salida));
            else
                return Carbon::parse($this->entrada)->diffInHours(Carbon::now());
        else
            return 0;
    }


    public function getNombreEmpleadoAttribute()
    {
        return $this->empleado()->pluck('nombre')->first();
    }

    public function empleado(){
        return $this->belongsTo('App\Models\Empleados\Empleados\Empleado', 'empleado_id');
    }


}



