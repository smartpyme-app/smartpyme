<?php

namespace App\Models\Restaurante;

use App\Models\Inventario\Producto;
use Illuminate\Database\Eloquent\Model;

class OrdenDetalle extends Model
{
    protected $table = 'orden_detalle_restaurante';

    protected $fillable = [
        'sesion_id',
        'producto_id',
        'cantidad',
        'precio_unitario',
        'notas',
        'enviado_cocina',
    ];

    protected $casts = [
        'enviado_cocina' => 'boolean',
    ];

    public function sesion()
    {
        return $this->belongsTo(SesionMesa::class, 'sesion_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function comandaDetalles()
    {
        return $this->hasMany(ComandaDetalle::class, 'orden_detalle_id');
    }
}
