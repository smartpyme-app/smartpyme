<?php

namespace App\Models\Planilla;

use App\Constants\PlanillaConstants;
use App\Models\Admin\Empresa;
use App\Models\Compras\Gastos\AreaEmpresa;
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

    protected $appends = ['sucursalNombre', 'areas_count'];

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

    public function areas()
    {
        return $this->hasMany(AreaEmpresa::class, 'id_departamento');
    }

    public function areasActivas()
    {
        return $this->areas()->where('activo', true)->where('estado', 1);
    }

    public function getAreasCountAttribute()
    {
        return $this->areas()->count();
    }

    public function empleadosActivos()
    {
        return $this->empleados()->where('estado', 1);
    }

    public function getSucursalNombreAttribute()
    {
        return $this->sucursal()->first()->nombre;
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
