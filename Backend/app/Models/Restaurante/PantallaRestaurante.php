<?php

namespace App\Models\Restaurante;

use App\Models\Inventario\Producto;
use Illuminate\Database\Eloquent\Model;

class PantallaRestaurante extends Model
{
    protected $table = 'restaurante_pantallas';

    protected $fillable = [
        'id_empresa',
        'nombre',
        'orden',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function productos()
    {
        return $this->belongsToMany(
            Producto::class,
            'producto_restaurante_pantalla',
            'pantalla_id',
            'producto_id'
        );
    }

    public function comandas()
    {
        return $this->hasMany(Comanda::class, 'pantalla_id');
    }
}
