<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;
class Bodega extends Model {

    protected $table = 'sucursal_bodegas';
    protected $fillable = array(
        'nombre',
        'descripcion',
        'activo',
        'id_sucursal',
        'id_empresa'
    );

    protected $appends = ['nombre_sucursal'];
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
    
    public function getNombreSucursalAttribute()
    {
        return $this->sucursal()->pluck('nombre')->first();
    }

    public function productos(){
        return $this->hasMany('App\Models\Inventario\Inventario', 'id_bodega');
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal', 'id_sucursal');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }


}



