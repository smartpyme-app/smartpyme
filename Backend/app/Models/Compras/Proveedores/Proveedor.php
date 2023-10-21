<?php

namespace App\Models\Compras\Proveedores;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;

class Proveedor extends Model {
    
    // use SoftDeletes;
    protected $table = 'proveedores';
    protected $fillable = array(
        'nombre',
        'registro',
        'dui',
        'nit',
        'giro',
        'descripcion',
        'direccion',
        'municipio',
        'departamento',
        'telefono',
        'tipo_contribuyente',
        'correo',
        'etiquetas',
        'nota',
        'usuario_id',
        'empresa_id'
    );


    public function getEtiquetasAttribute($value) 
    {
        return is_string($value) ? json_decode($value) : $value;
    }

    public function categoria(){
        return $this->belongsTo('App\Models\Admin\Categoria', 'categoria_id');
    }

    public function comprasPendientes(){
        return $this->hasMany('App\Models\Compras\Compra', 'proveedor_id')->where('estado', 'Pendiente');
    }

    public function compras(){
        return $this->hasMany('App\Models\Compras\Compra', 'proveedor_id');
    }

}

