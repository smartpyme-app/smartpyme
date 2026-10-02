<?php

namespace App\Models\Admin;

use App\Models\Concerns\AuditableModel;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class Impuesto extends AuditableModel {

    protected static function auditModule(): string
    {
        return 'configuraciones';
    }

    protected $table = 'impuestos';
    protected $fillable = array(
        'nombre',
        'porcentaje',
        'id_cuenta_contable_ventas',
        'id_cuenta_contable_compras',
        'codigo_mh',
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

        static::addGlobalScope('empresa', function (Builder $builder) {
            $user = Auth::guard('api')->user() ?? Auth::user();
            if (!$user) {
                return;
            }
            $builder->where('id_empresa', $user->id_empresa);
        });
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }


}
