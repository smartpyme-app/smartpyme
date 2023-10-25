<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Proveedor extends Model
{
    protected $table = 'producto_proveedores';
    protected $fillable = [
        'id_producto',
        'id_proveedor',
    ];

    protected $appends = ['nombre_proveedor'];

    public function getNombreProveedorAttribute(){
        return $this->proveedor()->pluck('nombre')->first();
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto', 'id_producto');
    }

    public function proveedor(){
        return $this->belongsTo('App\Models\Compras\Proveedores\Proveedor', 'id_proveedor');
    }

}
