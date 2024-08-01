<?php

namespace App\Models\Bancos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class Transaccion extends Model
{
    use HasFactory;
    protected $table = 'cuentas_bancarias_transacciones';
    protected $fillable = [
        'fecha',
        'id_cuenta',
        'concepto',
        'tipo',
        'estado',
        'tipo_operacion',
        'referencia',
        'id_referencia',
        'url_referencia',
        'total',
        'id_empresa',
        'id_usuario',
    ];

    protected $appends = ['nombre_usuario', 'ruta_referencia'];
    
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

    public function getRutaReferenciaAttribute(){
        if ($this->referencia == 'Venta') {
            return 'venta';
        }
        if ($this->referencia == 'Abono de Venta') {
            return 'venta/abono';
        }
        if ($this->referencia == 'Abono de Compra') {
            return 'compra/abono';
        }
        if ($this->referencia == 'Compra') {
            return 'compra';
        }
        if ($this->referencia == 'Cheque') {
            return 'bancos/cheque';
        }

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
