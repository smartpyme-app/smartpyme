<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use JWTAuth;

class Sucursal extends Model {

    protected $table = 'sucursales';
    protected $fillable = [
        'nombre',
        'telefono',
        'correo',
        'direccion',
        'municipio',
        'departamento',
        'direccion',
        'id_empresa',
    ];

    protected static function booted()
    {
        $usuario = JWTAuth::parseToken()->authenticate();

        if ($usuario){
            static::addGlobalScope('empresa', function (Builder $builder) use ($usuario) {
                $builder->where('id_empresa', $usuario->id_empresa);
            });
        }
        
    }

    public function cajas(){
        return $this->hasMany('App\Models\Admin\Caja', 'id_sucursal');
    }

    public function usuarios(){
        return $this->hasMany('App\Models\User', 'id_sucursal');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }


}
