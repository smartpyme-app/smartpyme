<?php

namespace App\Models\Compras\Proveedores;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
// use Illuminate\Database\Eloquent\SoftDeletes;
use JWTAuth;

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
        'id_usuario',
        'id_empresa'
    );

    protected static function booted()
    {
        $usuario = JWTAuth::parseToken()->authenticate();

        if ($usuario){
            static::addGlobalScope('empresa', function (Builder $builder) use ($usuario) {
                $builder->where('id_empresa', $usuario->id_empresa);
            });
        }
    }


    public function getEtiquetasAttribute($value) 
    {
        return is_string($value) ? json_decode($value) : $value;
    }

    public function categoria(){
        return $this->belongsTo('App\Models\Admin\Categoria', 'id_categoria');
    }

    public function comprasPendientes(){
        return $this->hasMany('App\Models\Compras\Compra', 'id_proveedor')->where('estado', 'Pendiente');
    }

    public function compras(){
        return $this->hasMany('App\Models\Compras\Compra', 'id_proveedor');
    }

}

