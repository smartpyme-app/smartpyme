<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;

class VentaProgramadaEjecucion extends Model
{
    protected $table = 'ventas_programadas_ejecuciones';

    protected $fillable = [
        'id_venta_programada',
        'id_venta',
        'periodo',
        'programada_para',
        'ejecutada_en',
        'estado',
        'modo',
        'mensaje_error',
    ];

    protected $casts = [
        'programada_para' => 'datetime',
        'ejecutada_en' => 'datetime',
    ];

    public function ventaProgramada()
    {
        return $this->belongsTo(VentaProgramada::class, 'id_venta_programada');
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'id_venta');
    }
}