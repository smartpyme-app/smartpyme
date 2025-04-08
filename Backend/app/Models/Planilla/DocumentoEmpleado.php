<?php

namespace App\Models\Planilla;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentoEmpleado extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'documentos_empleado';

    protected $fillable = [
        'id_empleado',
        'tipo_documento',
        'nombre_archivo',
        'ruta_archivo', 
        'fecha_documento',
        'fecha_vencimiento',
        'estado'
    ];

    protected $dates = [
        'fecha_documento',
        'fecha_vencimiento'
    ];

    public function empleado()
    {
        return $this->belongsTo('App\Models\Planilla\Empleado', 'id_empleado');
    }

    // Scopes
    public function scopeActivo($query)
    {
        return $query->where('estado', 1);
    }
}
