<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class Ajuste extends Model {

    protected $table = 'ajustes';
    protected $fillable = array(
        'concepto',
        'id_producto',
        'id_bodega',
        'stock_actual',
        'stock_real',
        'ajuste',
        'estado',
        'id_empresa',
        'id_usuario',
    );

    protected $appends = ['nombre_usuario', 'nombre_producto', 'nombre_bodega'];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }

    public function getNombreUsuarioAttribute()
    {
        return $this->usuario()->pluck('name')->first();
    }

    public function getNombreProductoAttribute()
    {
        return  $this->producto()->first() ? $this->producto()->pluck('nombre')->first() : '';
    }

    public function getNombreBodegaAttribute()
    {
        return  $this->bodega()->first() ? $this->bodega()->pluck('nombre')->first() : '';
    }

    public function bodega(){
        return $this->belongsTo('App\Models\Inventario\Bodega','id_bodega');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa','id_empresa');
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','id_producto');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','id_usuario');
    }

}



