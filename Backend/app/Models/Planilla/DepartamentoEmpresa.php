<?php

namespace App\Models\Planilla;

use App\Constants\PlanillaConstants;
use App\Models\Admin\Empresa;
use App\Models\Planilla\Empleado;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DepartamentoEmpresa extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'nombre',
        'descripcion', 
        'activo',
        'estado',
        'id_sucursal',
        'id_empresa',

    ];

    protected $table = 'departamentos_empresa';

    public function sucursal()
    {
        return $this->belongsTo('App\Models\Admin\Sucursal', 'id_sucursal');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function empleados()
    {
        return $this->hasMany(Empleado::class);
    }

    // Obtener empleados activos del departamento
    public function empleadosActivos()
    {
        return $this->empleados()->where('estado', 1);
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
}
