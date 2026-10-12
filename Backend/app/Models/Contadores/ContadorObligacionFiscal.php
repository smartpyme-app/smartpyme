<?php

namespace App\Models\Contadores;

use Illuminate\Database\Eloquent\Model;

class ContadorObligacionFiscal extends Model
{
    protected $table = 'contador_obligaciones_fiscales';

    protected $fillable = [
        'id_empresa',
        'codigo',
        'mes',
        'anio',
        'presentado_en',
        'id_usuario_presento',
    ];

    protected $casts = [
        'presentado_en' => 'datetime',
    ];
}
