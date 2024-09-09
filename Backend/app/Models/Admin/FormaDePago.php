<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class FormaDePago extends Model {

    protected $table = 'formas_pago';
    protected $fillable = array(
        'nombre',
        'orden',
        'id_empresa',
        'id_banco',

    );

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }

    public function banco(){
        return $this->belongsTo('App\Models\Bancos\Cuenta', 'id_banco');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }


}
