<?php

namespace App\Models\Inventario\Precios;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class Precio extends Model
{
    protected $table = 'producto_precios';
    protected $fillable = [
        'precio',
        'id_producto',
    ];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            $usuario = Auth::user();
            if ($usuario->tipo == 'Ventas') {
                static::addGlobalScope('permiso', function (Builder $builder) use ($usuario) {
                    $builder->whereHas('usuarios', function($q) use ($usuario){
                            return $q->where('id_usuario', $usuario->id);
                        });
                });
            }
        }
    }

    public function usuarios(){
        return $this->hasMany('App\Models\Inventario\Precios\Usuario', 'id_precio');
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto', 'producto_id');
    }

}
