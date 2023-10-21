<?php

namespace App\Models\Empleados\Planillas;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Models\Admin\Empresa;

class Detalle extends Model {

    protected $table = 'empleados_planilla_detalles';
    protected $fillable = array(
        'empleado_id',
        'dias_trabajados',
        'horas_trabajadas',
        'sueldo_base',
        'horas_extras',
        'comisiones',
        'otros_ingresos',
        'bonificaciones',
        'vacaciones',
        'indemnizacion',
        'aguinaldo',
        'total_bruto',
        'isss_patronal',
        'afp_patronal',
        'isss',
        'afp',
        'renta',
        'anticipos',
        'prestamos',
        'institucion_financiera',
        'otros_descuentos',
        'total_descuentos',
        'total',
        'planilla_id'
    );

    public $appends = ['nombre_empleado'];

    public function getNombreEmpleadoAttribute(){
        return $this->empleado()->pluck('nombre')->first();
    }

    public function empleado(){
        return $this->belongsTo('App\Models\Empleados\Empleados\Empleado', 'empleado_id');
    }

    public function planilla(){
        return $this->belongsTo('App\Models\Empleados\Planillas\Planilla', 'planilla_id');
    }


}



