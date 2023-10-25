<?php

namespace App\Models\Compras\Devoluciones;

use Illuminate\Database\Eloquent\Model;

class Devolucion extends Model {

    protected $table = 'devoluciones_compra';
    protected $fillable = array(
        'fecha',
        'estado',
        'referencia',
        'id_proveedor',
        'descuento',
        'subtotal',
        'no_sujeta',
        'exenta',
        'gravada',
        'iva_percibido',
        'iva_retenido',
        'iva',
        'total',
        'nota',
        'id_compra',
        'id_usuario',
        'id_empresa',
    );

    protected $appends = ['nombre_proveedor', 'nombre_usuario'];


    public function getNombreProveedorAttribute()
    {
        return $this->proveedor()->pluck('nombre')->first();
    }

    public function getNombreUsuarioAttribute()
    {
        return $this->usuario()->pluck('name')->first();
    }

    public function proveedor(){
        return $this->belongsTo('App\Models\Compras\Proveedores\Proveedor','id_proveedor');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','id_usuario');
    }

    public function compra(){
        return $this->belongsTo('App\Models\Compras\Compra','id_compra');
    }

    public function detalles(){
        return $this->hasMany('App\Models\Compras\Devoluciones\Detalle','id_devolucion');
    }


}
