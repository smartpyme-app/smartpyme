<?php

namespace App\Models\Restaurante;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ItemEliminacionLog extends Model
{
    protected $table = 'rest_item_eliminaciones_log';

    protected $fillable = [
        'orden_detalle_id',
        'sesion_id',
        'producto_id',
        'cantidad',
        'precio_unitario',
        'notas',
        'enviado_cocina',
        'enviado_barra',
        'motivo_codigo',
        'motivo_detalle',
        'usuario_id',
        'autorizado_usuario_id',
    ];

    protected $casts = [
        'enviado_cocina' => 'boolean',
        'enviado_barra' => 'boolean',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function autorizadoPor()
    {
        return $this->belongsTo(User::class, 'autorizado_usuario_id');
    }
}
