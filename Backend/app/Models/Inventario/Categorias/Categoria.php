<?php

namespace App\Models\Inventario\Categorias;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use JWTAuth;

class Categoria extends Model
{
    protected $table = 'categorias';
    protected $fillable = array(
        'nombre',
        'img',
        'descripcion',
        'enable',
        'id_empresa'
    );

    protected $casts = ['enable' => 'string'];

    protected static function booted()
    {
        $usuario = JWTAuth::parseToken()->authenticate();

        if ($usuario){
            static::addGlobalScope('empresa', function (Builder $builder) use ($usuario) {
                $builder->where('id_empresa', $usuario->id_empresa);
            });
        }
        
    }

    public function productos(){
        return $this->hasMany(Producto::class, 'id_categoria');
    }

}
