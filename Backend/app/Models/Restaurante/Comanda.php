<?php

namespace App\Models\Restaurante;

use Illuminate\Database\Eloquent\Model;

class Comanda extends Model
{
    protected $table = 'comandas_restaurante';

    protected $fillable = [
        'sesion_id',
        'pedido_id',
        'numero_comanda',
        'estado',
        'destino',
        'eliminacion_item_enviado',
        'motivo_eliminacion_codigo',
        'motivo_eliminacion_detalle',
        'enviado_at',
    ];

    protected $casts = [
        'enviado_at' => 'datetime',
        'eliminacion_item_enviado' => 'boolean',
    ];

    public function sesion()
    {
        return $this->belongsTo(SesionMesa::class, 'sesion_id');
    }

    public function pedido()
    {
        return $this->belongsTo(PedidoRestaurante::class, 'pedido_id');
    }

    public function detalles()
    {
        return $this->hasMany(ComandaDetalle::class, 'comanda_id');
    }
}
