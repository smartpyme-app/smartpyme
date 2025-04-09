<?php

namespace App\Models\Planilla;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactoEmergencia extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'contactos_emergencia';

    protected $fillable = [
        'id_empleado',
        'nombre',
        'relacion',
        'telefono',
        'direccion',
        'estado'
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
