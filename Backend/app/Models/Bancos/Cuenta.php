<?php

namespace App\Models\Bancos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class Cuenta extends Model
{
    use HasFactory;
    protected $table = 'cuentas_bancarias';
    protected $fillable = [
        'numero',
        'nombre_banco',
        'tipo',
        'correlativo_cheques',
        'saldo',
        'id_cuenta_contable',
        'id_empresa',
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

    public function transacciones(){
        return $this->hasMany('App\Models\Bancos\Transaccion', 'id_cuenta');
    }

}
