<?php

namespace App\Models\Inventario\Composiciones;

use Illuminate\Database\Eloquent\Model;

class Composicion extends Model {

    protected $table = 'producto_composiciones';
    protected $fillable = array(
        'id_producto',
        'id_compuesto',
        'cantidad',
        'id_presentacion'
    );

    protected $appends = ['nombre_producto', 'nombre_compuesto', 'precio', 'costo'];

    public function getNombreCompuestoAttribute(){
        $nombre = $this->relationLoaded('compuesto')
            ? ($this->compuesto ? $this->compuesto->nombre : null)
            : $this->compuesto()->pluck('nombre')->first();
        if ($this->id_presentacion) {
            $presentacion = $this->relationLoaded('presentacion')
                ? $this->presentacion
                : $this->presentacion()->first();
            if ($presentacion && $presentacion->nombre_comercial) {
                $nombre = $nombre . ' (' . $presentacion->nombre_comercial . ')';
            }
        }
        return $nombre;
    }

    public function getNombreProductoAttribute(){
        if ($this->relationLoaded('compuesto')) {
            return $this->compuesto ? $this->compuesto->nombre : null;
        }

        return $this->producto()->pluck('nombre')->first();
    }

    public function getPrecioAttribute(){
        if ($this->relationLoaded('compuesto')) {
            return $this->compuesto ? $this->compuesto->precio : null;
        }

        return $this->producto()->pluck('precio')->first();
    }

    public function getCostoAttribute(){
        if ($this->relationLoaded('compuesto')) {
            return $this->compuesto ? $this->compuesto->costo : null;
        }

        return $this->producto()->pluck('costo')->first();
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','id_compuesto');
    }

    public function compuesto(){
        return $this->belongsTo('App\Models\Inventario\Producto','id_compuesto');
    }

    public function opciones(){
        return $this->hasMany('App\Models\Inventario\Composiciones\Opcion','id_composicion');
    }

    public function presentacion(){
        return $this->belongsTo('App\Models\Inventario\ProductoPresentacion','id_presentacion');
    }


}
