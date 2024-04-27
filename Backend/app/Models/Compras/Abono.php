<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use JWTAuth;

class Abono extends Model {

    protected $table = 'abonos_compras';
    protected $fillable = array(
        'fecha',
        'concepto',
        'referencia',
        'estado',
        'nombre_de',
        'forma_pago',
        'detalle_banco',
        'mora',
        'comision',
        'total',
        'nota',
        'id_caja',
        'id_corte',
        'id_compra',
        'id_usuario',
        'id_sucursal',
        'id_empresa',
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

    public function compra(){
        return $this->belongsTo('App\Models\Compras\Compra','id_compra');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','id_usuario');
    }

    public function sucursal(){
        return $this->belongsTo('App\Models\Admin\Sucursal','id_sucursal');
    }


}
