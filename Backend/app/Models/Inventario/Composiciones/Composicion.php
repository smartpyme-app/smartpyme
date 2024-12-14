<?php

namespace App\Models\Inventario\Composiciones;

use Illuminate\Database\Eloquent\Model;

class Composicion extends Model {

    protected $table = 'producto_composiciones';
    protected $fillable = array(
        'id_producto',
        'id_compuesto',
        'cantidad'
    );

    protected $appends = ['nombre_producto', 'nombre_compuesto', 'precio', 'costo'];

    public function getNombreCompuestoAttribute(){
        return $this->compuesto()->pluck('nombre')->first();
    }

    public function getNombreProductoAttribute(){
        return $this->producto()->pluck('nombre')->first();
    }
    
    public function getPrecioAttribute(){
        return $this->producto()->pluck('precio')->first();
    }
    
    public function getCostoAttribute(){
        return $this->producto()->pluck('costo')->first();
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','id_producto');
    }

    public function compuesto(){
        return $this->belongsTo('App\Models\Inventario\Producto','id_compuesto');
    }

    public function opciones(){
        return $this->hasMany('App\Models\Inventario\Composiciones\Opcion','id_composicion');
    }


}
