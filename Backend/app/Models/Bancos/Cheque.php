<?php

namespace App\Models\Bancos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class Cheque extends Model
{
    use HasFactory;
    protected $table = 'cuentas_bancarias_cheques';
    protected $fillable = [
        'fecha',
        'id_cuenta',
        'correlativo',
        'anombrede',
        'concepto',
        'estado',
        'total',
        'id_usuario',
        'id_empresa',
    ];

    protected $appends = ['nombre_usuario', 'nombre_cuenta'];
    
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

    public function getNombreCuentaAttribute()
    {   
        return $this->cuenta()->pluck('nombre_banco')->first();
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
