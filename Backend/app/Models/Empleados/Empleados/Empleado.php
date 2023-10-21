<?php

namespace App\Models\Empleados\Empleados;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Empleado extends Model {

    use SoftDeletes;
    protected $table = 'empleados';
    protected $fillable = [
        'img',
        'nombre',
        'fecha_nacimiento',
        'genero',
        'dui',
        'telefono',
        'correo',
        'municipio',
        'departamento',
        'direccion',
        'pais',
        'nacionalidad',
        'activo',
        'num_licencia',
        'fecha_vencimiento',
        'tipo_licencia',
        'cargo_id',
        'fecha_inicio',
        'estado',
        'sueldo',
        'tipo_salario',
        'isss',
        'afp',
        'renta',
        'tipo_comision',
        'comision',
        'contacto_nombre',
        'contacto_telefono',
        'nota',
        'sucursal_id',
    ];

    protected $appends = ['nombre_sucursal', 'nombre_cargo', 'nombre_cargo', 'dias_trabajados', 'horas_trabajadas', 'total_comisiones', 'total_fletes'];
    protected $casts = ['activo' => 'boolean', 'isss' => 'boolean', 'afp' => 'boolean', 'renta' => 'boolean'];
    // protected $dates = ['fecha_nacimiento', 'fecha_vencimiento', 'fecha_inicio'];


    public function getHorasTrabajadasAttribute(){
        return 0;
        return $this->asistencias()->whereMonth('created_at', date('m'))->whereYear('created_at', date('Y'))->get()->sum('horas_laborales');
    }

    public function getDiasTrabajadosAttribute(){
        if ($this->tipo_salario == 'Quincenal') {
            return 15;
        }
        if ($this->tipo_salario == 'Mensual') {
            return 30;
        }
        return 0;
        return $this->asistencias()->whereMonth('created_at', date('m'))->whereYear('created_at', date('Y'))->count();
    }

    public function getTotalComisionesAttribute(){
        return $this->comisiones()->whereMonth('fecha', date('m'))->whereYear('fecha', date('Y'))->where('estado', 'Pendiente')->sum('total');
    }

    public function getTotalFletesAttribute(){
        return $this->fletes()->whereBetween('fecha', [now()->startOfWeek(), now()->endOfWeek()])->sum('motorista');
    }

    public function getNombreSucursalAttribute(){
        return $this->sucursal()->pluck('nombre')->first();
    }

    public function getNombreCargoAttribute(){
        return $this->cargo()->pluck('nombre')->first();
    }

    public function scopeMotoristas($query){
        return $query->where('cargo_id', 1);
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal', 'sucursal_id');
    }

    public function cargo(){
        return $this->belongsTo('App\Models\Empleados\Cargo', 'cargo_id');
    }

    public function cortes(){
        return $this->hasMany('App\Models\Admin\Corte', 'usuario_id');
    }

    public function ventas(){
        return $this->hasMany('App\Models\Ventas\Venta', 'vendedor_id')->where('estado', 'Pagada');
    }

    public function fletes(){
        return $this->hasMany('App\Models\Transporte\Fletes\Flete', 'motorista_id');
    }

    public function asistencias(){
        return $this->hasMany('App\Models\Empleados\Asistencia', 'usuario_id');
    }

    public function metas(){
        return $this->hasMany('App\Models\Empleados\Empleados\Meta', 'empleado_id');
    }

    public function comisiones(){
        return $this->hasMany('App\Models\Empleados\Empleados\Comision', 'empleado_id');
    }

    public function documentos(){
        return $this->hasMany('App\Models\Empleados\Empleados\Documento', 'empleado_id');
    }

    public function asistenciaDiaria(){
        return $this->hasOne('App\Models\Empleados\Empleados\Asistencia', 'empleado_id')->whereDate('fecha', Carbon::today());
    }

    public function asistenciaMensual(){
        return $this->hasMany('App\Models\Empleados\Empleados\Asistencia', 'empleado_id')->whereMonth('fecha', date('m'))->whereYear('fecha', date('Y'));
    }

    public function compras(){
        return $this->hasMany('App\Models\Compras\Compra', 'usuario_id');
    }

    public function cuenta(){
        return $this->hasOne('App\Models\User', 'empleado_id');
    }


}
