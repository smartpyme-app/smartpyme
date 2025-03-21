<?php

namespace App\Models\Planilla;

use App\Constants\PlanillaConstants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HistorialBaja extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'historial_bajas';

    protected $fillable = [
        'id_empleado',
        'fecha_baja',
        'tipo_baja',
        'motivo',
        'documento_respaldo',
        'estado'
    ];

    protected $dates = [
        'fecha_baja'
    ];

    public function empleado()
    {
        return $this->belongsTo('App\Models\Planilla\Empleado', 'id_empleado');
    }

    public function cargo()
    {
        return $this->belongsTo('App\Models\Planilla\CargoEmpresa', 'id_cargo');
    }

    // Scopes
    public function scopeActivo($query)
    {
        return $query->where('estado', 1);
    }

    public function documento_empleado()
    {
        return $this->belongsTo('App\Models\Planilla\DocumentoEmpleado', 'documento_respaldo');
    }
}
