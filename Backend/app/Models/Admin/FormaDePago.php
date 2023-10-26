<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use JWTAuth;

class FormaDePago extends Model {

    protected $table = 'formas_pago';
    protected $fillable = array(
        'nombre',
        'orden',
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

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }


}
