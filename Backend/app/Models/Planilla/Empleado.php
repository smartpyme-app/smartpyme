<?php

namespace App\Models\Planilla;

use App\Constants\PlanillaConstants;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empleado extends Model
{
    use HasFactory, SoftDeletes;

    const ESTADO_EMPLEADO_ACTIVO = PlanillaConstants::ESTADO_EMPLEADO_ACTIVO;//1
    const ESTADO_EMPLEADO_INACTIVO = PlanillaConstants::ESTADO_EMPLEADO_INACTIVO;//2

    const TIPO_CONTRATO_PERMANENTE = PlanillaConstants::TIPO_CONTRATO_PERMANENTE;//1
    const TIPO_CONTRATO_TEMPORAL = PlanillaConstants::TIPO_CONTRATO_TEMPORAL;//2
    const TIPO_CONTRATO_POR_OBRA = PlanillaConstants::TIPO_CONTRATO_POR_OBRA;//3

    const TIPO_JORNADA_TIEMPO_COMPLETO = PlanillaConstants::TIPO_JORNADA_TIEMPO_COMPLETO;//1
    const TIPO_JORNADA_MEDIO_TIEMPO = PlanillaConstants::TIPO_JORNADA_MEDIO_TIEMPO;//2

    protected $fillable = [
        'codigo',
        'nombres', 
        'apellidos',
        'dui',
        'nit',
        'isss',
        'afp',
        'fecha_nacimiento',
        'direccion',
        'telefono',
        'email',
        'salario_base',
        'tipo_contrato',
        'tipo_jornada',
        'fecha_ingreso',
        'fecha_fin',
        'fecha_baja',
        'estado',
        'id_departamento',
        'id_cargo',
        'id_sucursal',
        'id_empresa'
    ];


    // Relaciones
    public function sucursal()
    {
        return $this->belongsTo('App\Models\Admin\Sucursal', 'id_sucursal');
    }

    public function departamento()
    {
        return $this->belongsTo('App\Models\Planilla\DepartamentoEmpresa', 'id_departamento');
    }

    public function cargo()
    {
        return $this->belongsTo('App\Models\Planilla\CargoEmpresa', 'id_cargo');
    }

    public function contacto_emergencia()
    {
        return $this->hasOne('App\Models\Planilla\ContactoEmergencia', 'id_empleado');
    }

    public function historial_contrato()
    {
        return $this->hasMany('App\Models\Planilla\HistorialContrato', 'id_empleado');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    // Accessors
    public function getNombreCompletoAttribute()
    {
        return "{$this->nombres} {$this->apellidos}";
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('estado', 1);
    }

    public function scopeInactivos($query)
    {
        return $query->where('estado', 0);
    }

    public function scopePorSucursal($query, $sucursalId)
    {
        return $query->where('id_sucursal', $sucursalId);
    }

    public function scopePorDepartamento($query, $departamentoId)
    {
        return $query->where('id_departamento', $departamentoId);
    }

    public function aniosServicio()
    {
        return Carbon::parse($this->fecha_ingreso)->diffInYears(Carbon::now());
    }
}
