<?php

namespace App\Models\Transporte\Fletes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use JWTAuth;

class Detalle extends Model {

    protected $table = 'transporte_flete_detalles';
    protected $fillable = array(
       'descripcion',
       'peso',
       'unidades',
       'bultos',
       'valor_carga',
       'tipo_embalaje',
       'flete_id',
    );

    protected $appends = [];

    // protected static function booted()
    // {
    //     $usuario = JWTAuth::parseToken()->authenticate();

    //     if ($usuario && $usuario->tipo != 'Administrador'){
    //         static::addGlobalScope('sucursal', function (Builder $builder) use ($usuario) {
    //             $builder->where('sucursal_id', $usuario->sucursal_id);
    //         });
    //     }
    // }



    public function flete(){
        return $this->belongsTo('App\Models\Transporte\Flete\Flete','flete_id');
    }


}
