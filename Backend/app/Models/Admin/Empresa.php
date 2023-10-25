<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;

class Empresa extends Model {

    // use SoftDeletes;
    protected $table = 'empresas';
    protected $fillable = [
        'nombre',
        'propietario',
        'sector',
        'giro',
        'nit',
        'registro',
        'tipo_contribuyente',
        'direccion',
        'telefono',
        'correo',
        'municipio',
        'departamento',
        'logo',
        'propina',
        'valor_inventario',
        'vender_sin_stock',
        'editar_precio_venta',
        'ips'
    ];

    public function getIpsAttribute($value) 
    {
        return is_string($value) ? json_decode($value) : $value;
    }


}
