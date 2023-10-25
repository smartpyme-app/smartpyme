<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class Ajuste extends Model {

    protected $table = 'ajustes';
    protected $fillable = array(
        'concepto',
        'id_producto',
        'id_sucursal',
        'stock_actual',
        'stock_real',
        'ajuste',
        'estado',
        'id_empresa',
        'id_usuario',
    );

    protected $appends = ['nombre_usuario', 'nombre_producto', 'nombre_sucursal'];

    public function getNombreUsuarioAttribute()
    {
        return $this->usuario()->first() ? $this->usuario()->pluck('name')->first() : '';
    }

    public function getNombreProductoAttribute()
    {
        return  $this->producto()->first() ? $this->producto()->pluck('nombre')->first() : '';
    }

    public function getNombreSucursalAttribute()
    {
        return  $this->sucursal()->first() ? $this->sucursal()->pluck('nombre')->first() : '';
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal','id_sucursal');
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','id_producto');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','id_usuario');
    }

}



