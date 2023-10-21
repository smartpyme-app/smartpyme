<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class Kardex extends Model {

    protected $table = 'producto_kardex';
    protected $fillable = array(
        'fecha',
        'producto_id',
        'bodega_id',
        'detalle',
        'referencia',
        'entrada_cantidad',
        'costo_unitario',
        'entrada_valor',
        'salida_cantidad',
        'precio_unitario',
        'salida_valor',
        'total',
        'usuario_id',
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
        return  $this->inventario()->first() ? $this->inventario()->first()->nombre_bodega : '';
    }

    public function getAjusteAttribute()
    {
        return $this->stock_final - $this->stock_inicial;
    }

    public function inventario(){
        return $this->belongsTo('App\Models\Inventario\Inventario','bodega_id');
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','producto_id')->withoutGlobalScopes();
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','usuario_id');
    }

}



