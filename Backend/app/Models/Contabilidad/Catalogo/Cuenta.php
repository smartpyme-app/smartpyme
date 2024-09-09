<?php

namespace App\Models\Contabilidad\Catalogo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class Cuenta extends Model
{
    use HasFactory;
    protected $table = 'catalogo_cuentas';
    protected $fillable = [
        'codigo',
        'nombre',
        'naturaleza',
        'id_cuenta_padre',
        'rubro',
        'nivel',

        'cargo',
        'abono',
        'saldo',
        
        'id_empresa',
        'acepta_datos',
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
