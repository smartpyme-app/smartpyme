<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class Kardex extends Model {

    protected $table = 'kardexs';
    protected $fillable = array(
        'fecha',
        'id_producto',
        'id_inventario',
        'detalle',
        'referencia',
        'entrada_cantidad',
        'costo_unitario',
        'entrada_valor',
        'salida_cantidad',
        'precio_unitario',
        'salida_valor',
        'total_cantidad',
        'total_valor',
        'id_usuario',
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
        return  $this->inventario()->first() ? $this->inventario()->first()->nombre : '';
    }

    public function getAjusteAttribute()
    {
        return $this->stock_final - $this->stock_inicial;
    }

    public function inventario(){
        return $this->belongsTo('App\Models\Inventario\Inventario','id_inventario');
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','id_producto')->withoutGlobalScopes();
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','id_usuario');
    }

}



