<?php

namespace App\Models\Inventario\Categorias;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class Categoria extends Model
{
    protected $table = 'categorias';
    protected $fillable = array(
        'nombre',
        'img',
        'descripcion',
        'enable',
        'id_empresa',
        'subcategoria',
        'id_cate_padre'
    );

    protected $casts = ['enable' => 'string'];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }

    public function cuentas(){
        return $this->hasMany('App\Models\Inventario\Categorias\Cuenta', 'id_categoria');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function productos(){
        return $this->hasMany(Producto::class, 'id_categoria');
    }

}
