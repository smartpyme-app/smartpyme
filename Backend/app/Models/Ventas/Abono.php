<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Abono extends Model
{

    protected $table = 'abonos_ventas';
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
        'id_venta',
        'id_usuario',
        'id_sucursal',
        'id_empresa',
    );
    protected $appends = ['correlativo'];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
        }
    }

    public function venta()
    {
        return $this->belongsTo('App\Models\Ventas\Venta', 'id_venta');
    }

    public function usuario()
    {
        return $this->belongsTo('App\Models\User', 'id_usuario');
    }

    public function sucursal()
    {
        return $this->belongsTo('App\Models\Admin\Sucursal', 'id_sucursal');
    }

    public function getCorrelativoAttribute()
    {

        $position = static::where('id_empresa', $this->id_empresa)
            ->where('id', '<=', $this->id)
            ->count();

        return  str_pad($position, 5, '0', STR_PAD_LEFT);
    }
}
