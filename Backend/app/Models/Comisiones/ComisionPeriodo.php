<?php

namespace App\Models\Comisiones;

use App\Models\Admin\Empresa;
use Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ComisionPeriodo extends Model
{
    protected $table = 'comision_periodos';

    const ESTADO_ABIERTO = 'abierto';
    const ESTADO_CERRADO = 'cerrado';
    const ESTADO_PAGADO = 'pagado';

    protected $fillable = [
        'id_empresa',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
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

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function movimientos()
    {
        return $this->hasMany(ComisionMovimiento::class, 'id_periodo');
    }

    public function liquidaciones()
    {
        return $this->hasMany(ComisionLiquidacion::class, 'id_periodo');
    }
}
