<?php

namespace App\Models\Compras\Devoluciones;

use Illuminate\Database\Eloquent\Model;

class Devolucion extends Model {

    protected $table = 'compras_devoluciones';
    protected $fillable = array(
        'fecha',
        'estado',
        'referencia',
        'proveedor_id',
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
        'compra_id',
        'usuario_id',
        'empresa_id',
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
        return $this->belongsTo('App\Models\Compras\Proveedores\Proveedor','proveedor_id');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','usuario_id');
    }

    public function compra(){
        return $this->belongsTo('App\Models\Compras\Compra','compra_id');
    }

    public function detalles(){
        return $this->hasMany('App\Models\Compras\Devoluciones\Detalle','devolucion_id');
    }


}
