<?php

namespace App\Models\PrestamosEmpresa;

use App\Models\Bancos\Cuenta;
use App\Models\User;
use Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrestamoEmpresa extends Model
{
    protected $table = 'prestamos_empresa';

    protected $fillable = [
        'id_empresa',
        'id_usuario',
        'tipo_acreedor',
        'acreedor',
        'concepto',
        'historico',
        'monto_original',
        'monto',
        'saldo',
        'genera_interes',
        'tasa_interes',
        'n_cuotas',
        'frecuencia',
        'fecha_desembolso',
        'fecha_primera_cuota',
        'id_cuenta_banco',
        'generar_asiento_desembolso',
        'clasificacion',
        'estado',
    ];

    protected $casts = [
        'historico' => 'boolean',
        'genera_interes' => 'boolean',
        'generar_asiento_desembolso' => 'boolean',
        'monto_original' => 'decimal:2',
        'monto' => 'decimal:2',
        'saldo' => 'decimal:2',
        'tasa_interes' => 'decimal:4',
        'fecha_desembolso' => 'date',
        'fecha_primera_cuota' => 'date',
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

    public function cuotas(): HasMany
    {
        return $this->hasMany(PrestamoCuota::class, 'id_prestamo')->orderBy('numero');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PrestamoPago::class, 'id_prestamo')->orderBy('id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function cuentaBanco(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class, 'id_cuenta_banco');
    }
}
