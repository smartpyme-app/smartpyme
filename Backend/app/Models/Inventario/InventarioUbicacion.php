<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class InventarioUbicacion extends Model
{
    protected $table = 'inventario_ubicaciones';

    protected $fillable = [
        'id_inventario',
        'ubicacion',
        'ubicacion_especifica',
        'detalle',
        'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    public function inventario()
    {
        return $this->belongsTo(Inventario::class, 'id_inventario');
    }
}
