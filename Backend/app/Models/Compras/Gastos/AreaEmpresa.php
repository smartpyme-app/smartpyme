<?php

namespace App\Models\Compras\Gastos;

use App\Models\Planilla\DepartamentoEmpresa;
use App\Models\Planilla\Empleado;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AreaEmpresa extends Model
{
 
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'nombre',
        'descripcion', 
        'activo',
        'estado',
        'id_departamento',
    ];

    protected $table = 'areas_empresa';

    public function departamento()
    {
        return $this->belongsTo(DepartamentoEmpresa::class, 'id_departamento');
    }

    // Relacion con empleados
    public function empleados()
    {
        return $this->hasMany(Empleado::class, 'id_area');
    }

    public function empleadosActivos()
    {
        return $this->empleados()->where('estado', 1);
    }

    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function scopeEstadoActivo($query)
    {
        return $query->where('estado', 1);
    }

    public function scopePorDepartamento($query, $departamentoId)
    {
        return $query->where('id_departamento', $departamentoId);
    }

    public function scopePorEmpresa($query, $empresaId)
    {
        return $query->where('id_empresa', $empresaId);
    }
}
