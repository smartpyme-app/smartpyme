<?php

namespace App\Models\Inventario\Precios;

use Illuminate\Database\Eloquent\Model;
use JWTAuth;

class Precio extends Model
{
    protected $table = 'producto_precios';
    protected $fillable = [
        'precio',
        'id_producto',
    ];

    protected static function booted()
    {
        $usuario = JWTAuth::parseToken()->authenticate();

        if ($usuario){

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
