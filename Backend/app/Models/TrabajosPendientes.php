<?php

namespace App\Models;

use App\Models\Admin\Empresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrabajosPendientes extends Model
{
    use HasFactory;

    protected $table = 'trabajos_pendientes';

    protected $fillable = [
        'tipo',
        'parametros',
        'estado',
        'fecha_creacion',
        'fecha_inicio',
        'fecha_fin',
        'resultado',
        'prioridad',
        'datos',
        'intentos',
        'max_intentos',
        'fecha_procesamiento',
        'id_usuario',
        'id_empresa'
    ];

    protected $casts = [
        'fecha_creacion' => 'datetime',
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
    ];

    /**
     * Obtener el usuario relacionado
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    /**
     * Obtener la empresa relacionada
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    /**
     * Scope para trabajos pendientes
     */
    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    /**
     * Determinar si el trabajo está en proceso
     */
    public function estaEnProceso()
    {
        return $this->estado === 'procesando';
    }

    /**
     * Determinar si el trabajo se ha completado
     */
    public function estaCompletado()
    {
        return $this->estado === 'completado';
    }

    /**
     * Determinar si el trabajo ha fallado
     */
    public function haFallado()
    {
        return $this->estado === 'fallido';
    }
}
