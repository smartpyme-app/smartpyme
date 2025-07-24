<?php

namespace App\Models\Compras\Gastos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use JWTAuth;

class Categoria extends Model {

    protected $table = 'gastos_categorias';
    protected $fillable = array(
        'nombre',
        'id_empresa',
        'id_cuenta_contable',
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

    public function cuenta(){
        return $this->belongsTo('App\Models\Contabilidad\Catalogo\Cuenta', 'id_cuenta_contable');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }



}



