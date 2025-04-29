<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

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
        'tipo_establecimiento',
        'cod_estable_mh',
        'activo',
        'id_empresa',
    ];

    protected $casts = [
        'activo' => 'string'
    ];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
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
