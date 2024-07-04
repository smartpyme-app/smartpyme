<?php

namespace App\Models\Bancos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class Conciliacion extends Model
{
    use HasFactory;
    protected $table = 'cuentas_bancarias_conciliaciones';
    protected $fillable = [
        'fecha',
        'desde',
        'hasta',
        'id_cuenta',
        'gastos',
        'impuestos',
        'otras_entradas',
        'saldo_anterior',
        'saldo_actual',
        'nota',
        'id_usuario',
        'id_empresa',
    ];

    protected $appends = ['nombre_usuario'];
    
    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }

    public function getNombreUsuarioAttribute()
    {   
        return $this->usuario()->pluck('name')->first();
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User', 'id_usuario');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function cuenta(){
        return $this->belongsTo('App\Models\Bancos\Cuenta', 'id_cuenta');
    }
    
}
