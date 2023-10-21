<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class Ajuste extends Model {

    protected $table = 'producto_ajustes';
    protected $fillable = array(
        'nota',
        'producto_id',
        'bodega_id',
        'stock_inicial',
        'stock_final',
        'usuario_id'
    );

    protected $appends = ['nombre_usuario', 'nombre_producto', 'nombre_bodega', 'ajuste'];

    public function getNombreUsuarioAttribute()
    {
        return $this->usuario()->first() ? $this->usuario()->pluck('name')->first() : '';
    }

    public function getNombreProductoAttribute()
    {
        return  $this->producto()->first() ? $this->producto()->pluck('nombre')->first() : '';
    }

    public function getNombreBodegaAttribute()
    {
        return  $this->bodega()->first() ? $this->bodega()->pluck('nombre')->first() : '';
    }

    public function getAjusteAttribute()
    {
        return $this->stock_final - $this->stock_inicial;
    }

    public function bodega(){
        return $this->belongsTo('App\Models\Inventario\Bodega','bodega_id');
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','producto_id');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','usuario_id');
    }

}



