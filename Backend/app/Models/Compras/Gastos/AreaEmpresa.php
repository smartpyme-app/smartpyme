<?php

namespace App\Models\Compras\Gastos;

use App\Models\Planilla\DepartamentoEmpresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AreaEmpresa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'areas_empresa';

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
        'estado',
        'id_departamento',
    ];

    public function departamento()
    {
        return $this->belongsTo(DepartamentoEmpresa::class, 'id_departamento');
    }

    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function scopeEstadoActivo($query)
    {
        return $query->where('estado', 1);
    }
}
