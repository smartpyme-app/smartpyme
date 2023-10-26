<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use JWTAuth;

class Canal extends Model {

    protected $table = 'canales';
    protected $fillable = array(
        'nombre',
        'descripcion',
        'enable',
        'cobra_propina',
        'envios',
        'id_empresa'
    );
    protected $casts = ['enable' => 'string'];

    protected static function booted()
    {
        $usuario = JWTAuth::parseToken()->authenticate();

        if ($usuario){
            static::addGlobalScope('empresa', function (Builder $builder) use ($usuario) {
                $builder->where('id_empresa', $usuario->id_empresa);
            });
        }
        
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa','id_empresa');
    }

    public function ventas(){
        return $this->hasMany('App\Models\Ventas\Venta','canal_id');
    }



}
