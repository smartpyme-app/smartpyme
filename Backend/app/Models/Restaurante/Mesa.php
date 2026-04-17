<?php

namespace App\Models\Restaurante;

use App\Models\Admin\Empresa;
use Illuminate\Database\Eloquent\Model;

class Mesa extends Model
{
    protected $table = 'restaurante_mesas';

    protected $fillable = [
        'id_empresa',
        'id_sucursal',
        'numero',
        'capacidad',
        'zona',
        'estado',
        'activo',
        'orden',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function sesiones()
    {
        return $this->hasMany(SesionMesa::class, 'mesa_id');
    }

    public function sesionActiva()
    {
        return $this->hasOne(SesionMesa::class, 'mesa_id')
            ->whereIn('estado', ['abierta', 'pre_cuenta']);
    }

    public function reservasActivas()
    {
        return $this->hasMany(Reserva::class, 'mesa_id')
            ->whereIn('estado', ['pendiente', 'confirmada'])
            ->where('fecha_reserva', '>=', now()->toDateString());
    }
}
