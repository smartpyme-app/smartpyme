<?php

namespace App\Models;

use App\Models\Inventario\Producto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetalleComboProducto extends Model
{
    use HasFactory;
    protected $table = 'detalles_combo_producto';
    protected $fillable = [
        'id_combo',
        'id_producto',
        'cantidad',
        'precio',
        'costo',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto');
    }
}
