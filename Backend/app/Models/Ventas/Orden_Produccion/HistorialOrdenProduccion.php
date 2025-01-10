<?php

namespace App\Models\Ventas\Orden_Produccion;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class HistorialOrdenProduccion extends Model
{
    protected $table = 'historial_orden_produccion';
    
    public $timestamps = false;

    protected $fillable = [
        'id_orden_produccion',
        'estado_anterior',
        'estado_nuevo',
        'id_usuario',
        'comentarios'
    ];

    // Relaciones
    public function orden()
    {
        return $this->belongsTo(OrdenProduccion::class, 'id_orden_produccion');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
}