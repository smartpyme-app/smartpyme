<?php

namespace App\Models\CreditosClientes;

use App\Models\User;
use App\Models\Ventas\Clientes\Cliente;
use Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditoContrato extends Model
{
    protected $table = 'credito_contratos';

    protected $fillable = [
        'id_empresa',
        'id_cliente',
        'id_usuario',
        'tipo',
        'monto',
        'n_cuotas',
        'fecha_inicio',
        'periodicidad',
        'tasa_interes',
        'tasa_mora',
        'concepto',
        'estado',
        'id_documento',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'tasa_interes' => 'decimal:4',
        'tasa_mora' => 'decimal:4',
        'fecha_inicio' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check() || Auth::guard('api')->check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $user = Auth::guard('api')->user() ?? Auth::user();
                if (!$user) {
                    return;
                }
                $builder->where('id_empresa', $user->id_empresa);
            });
        }
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function cuotas(): HasMany
    {
        return $this->hasMany(CreditoCuota::class, 'id_contrato')->orderBy('numero');
    }
}
