<?php

namespace App\Models\Restaurante;

use Illuminate\Database\Eloquent\Model;

class ComandaEstadoTiempo extends Model
{
    protected $table = 'comanda_estado_tiempos';

    protected $fillable = [
        'id_empresa',
        'comanda_id',
        'estado_desde',
        'estado_hasta',
        'inicio_at',
        'fin_at',
        'segundos',
    ];

    protected $casts = [
        'inicio_at' => 'datetime',
        'fin_at' => 'datetime',
        'segundos' => 'integer',
    ];
}
