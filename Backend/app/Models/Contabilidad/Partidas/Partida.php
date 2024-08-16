<?php

namespace App\Models\Contabilidad\Partidas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;

class Partida extends Model
{
    use HasFactory;
    protected $table = 'partidas';
    protected $fillable = [
        'fecha',
        'tipo',
        'concepto',
        'estado',
        'referencia',
        'id_referencia',
        'id_usuario',
        'id_empresa',
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

    public function getNombreUsuarioAttribute()
    {   
        return $this->usuario()->pluck('name')->first();
    }
    
    public function detalles(){
        return $this->hasMany('App\Models\Contabilidad\Partidas\Detalle', 'id_partida');
    }
    
    public function usuario(){
        return $this->belongsTo('App\Models\User', 'id_usuario');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }
    
}
