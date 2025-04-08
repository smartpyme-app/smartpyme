<?php

namespace App\Models\Planilla;

use App\Constants\PlanillaConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CargoEmpresa extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $table = 'cargos_de_empresa';

    protected $fillable = [
        'nombre',
        'descripcion',
        'salario_base',
        'activo',
        'estado',
        'id_sucursal',
        'id_empresa',
        'id_departamento'
    ];

    // Relación con sucursal
    public function sucursal()
    {
        return $this->belongsTo('App\Models\Admin\Sucursal', 'id_sucursal');
    }

    // Relación con empresa
    public function empresa()
    {
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function departamento()
    {
        return $this->belongsTo('App\Models\Planilla\DepartamentoEmpresa', 'id_departamento');
    }

    // Relación con historial de contratos
    public function historialContratos()
    {
        return $this->hasMany('App\Models\Planilla\HistorialContrato', 'id_cargo');
    }

    // Obtener empleados activos del cargo a través del historial de contratos
    public function empleadosActivos()
    {
        return $this->historialContratos()
                    ->whereNull('fecha_fin')
                    ->where('estado', 1);
    }

    // Scopes
    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function scopeEstadoActivo($query)
    {
        return $query->where('estado', 1);
    }

    // Obtener total de salarios actuales por cargo
    public function totalSalarios()
    {
        return $this->historialContratos()
                    ->whereNull('fecha_fin')
                    ->where('estado', 1)
                    ->sum('salario');
    }

    // Obtener promedio salarial actual del cargo
    public function promedioSalarial()
    {
        return $this->historialContratos()
                    ->whereNull('fecha_fin')
                    ->where('estado', 1)
                    ->avg('salario');
    }

    // Obtener el salario actual más alto del cargo
    public function salarioMaximo()
    {
        return $this->historialContratos()
                    ->whereNull('fecha_fin')
                    ->where('estado', 1)
                    ->max('salario');
    }

    // Obtener el salario actual más bajo del cargo
    public function salarioMinimo()
    {
        return $this->historialContratos()
                    ->whereNull('fecha_fin')
                    ->where('estado', 1)
                    ->min('salario');
    }
}
