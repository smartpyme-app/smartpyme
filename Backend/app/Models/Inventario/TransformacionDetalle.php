<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransformacionDetalle extends Model
{
    use HasFactory;

    protected $table = 'transformacion_detalles';

    protected $fillable = [
        'id_transformacion',
        'id_producto',
        'cantidad',
        'tipo',
    ];

    public function transformacion()
    {
        return $this->belongsTo(Transformacion::class, 'id_transformacion');
    }

    public function producto()
    {
        return $this->belongsTo('App\Models\Inventario\Producto', 'id_producto');
    }
}
