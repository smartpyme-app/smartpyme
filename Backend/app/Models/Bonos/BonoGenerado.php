<?php

namespace App\Models\Bonos;

use App\Models\Admin\Empresa;
use App\Models\User;
use Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BonoGenerado extends Model
{
    protected $table = 'bono_generados';

    const ESTADO_PENDIENTE = 'pendiente';
    const ESTADO_APROBADO = 'aprobado';
    const ESTADO_PAGADO = 'pagado';

    protected $fillable = [
        'id_empresa',
        'id_vendedor',
        'id_regla',
        'periodo_inicio',
        'periodo_fin',
        'monto_ventas_base',
        'monto',
        'estado',
        'aprobado_por',
        'aprobado_at',
        'pagado_at',
    ];

    protected $casts = [
        'periodo_inicio' => 'date',
        'periodo_fin' => 'date',
        'monto_ventas_base' => 'decimal:4',
        'monto' => 'decimal:4',
        'aprobado_at' => 'datetime',
        'pagado_at' => 'datetime',
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

    public function vendedor()
    {
        return $this->belongsTo(User::class, 'id_vendedor');
    }

    public function regla()
    {
        return $this->belongsTo(BonoRegla::class, 'id_regla');
    }

    public function aprobadoPor()
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }
}
