<?php

namespace App\Models\Empleados\Planillas;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Models\Admin\Empresa;

class Planilla extends Model {

    protected $table = 'empleados_planillas';
    protected $fillable = array(
        'fecha',        
        'tipo_planilla',        
        'tipo_contratacion',
        'estado',
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
        'nota',
        'usuario_id',
        'empresa_id'
    );

    public $appends = ['nombre_usuario'];

    public function getNombreUsuarioAttribute(){
        return $this->usuario()->pluck('name')->first();
    }


    public function detalles(){
        return $this->hasMany('App\Models\Empleados\Planillas\Detalle');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User', 'usuario_id');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'empresa_id');
    }


}



