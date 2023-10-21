<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class Composicion extends Model {

    protected $table = 'producto_composiciones';
    protected $fillable = array(
        'producto_id',
        'compuesto_id',
        'medida',
        'cantidad'
    );

    protected $appends = ['nombre_producto', 'nombre_compuesto'];

    public function getNombreProductoAttribute(){
        return $this->producto()->pluck('nombre')->first();
    }

    public function getNombreCompuestoAttribute(){
        return $this->compuesto()->pluck('nombre')->first();
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','producto_id');
    }

    public function compuesto(){
        return $this->belongsTo('App\Models\Inventario\Producto','compuesto_id');
    }


}