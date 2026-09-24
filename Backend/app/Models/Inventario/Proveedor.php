<?php

namespace App\Models\Inventario;

use App\Support\NombreComercial;
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

    public function getNombreProveedorAttribute()
    {   $proveedor = $this->proveedor()->first();
        if ($proveedor) {
            $legal = $proveedor->tipo == 'Persona' ? $proveedor->nombre . ' ' . $proveedor->apellido : $proveedor->nombre_empresa;

            return NombreComercial::anexar($legal, $proveedor->nombre_comercial);
        }
        return 'Consumidor Final';
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto', 'id_producto');
    }

    public function proveedor(){
        return $this->belongsTo('App\Models\Compras\Proveedores\Proveedor', 'id_proveedor');
    }

}
