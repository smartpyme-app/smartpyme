<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComboProducto extends Model
{
    use HasFactory;

    protected $table = 'combos_productos';
    protected $fillable = [
        'codigo_combo',
        'descripcion',
        'nombre',
        "id_sucursal",
        "id_empresa",
        "precio",
        "precio_total",
        "costo_total",
        "estado"
    ];

    public function detalles()
    {
        return $this->hasMany(DetalleComboProducto::class, 'id_combo');
    }
}
