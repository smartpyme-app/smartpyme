<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class Impuesto extends Model {

    protected $table = 'impuestos';
    protected $fillable = array(
        'nombre',
        'porcentaje',
        'id_cuenta_contable_ventas',
        'id_cuenta_contable_compras',
        'id_empresa',
        'aplica_ventas',
        'aplica_gastos',
        'aplica_compras'
    );

    protected $casts = [
        'aplica_ventas' => 'boolean',
        'aplica_gastos' => 'boolean',
        'aplica_compras' => 'boolean',
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

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }


}
