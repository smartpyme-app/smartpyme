<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Admin\Empresa;
use App\Models\User;
use Auth;

class SaldoMensual extends Model
{
    use HasFactory;

    protected $table = 'saldos_mensuales';

    protected $fillable = [
        'id_cuenta',
        'codigo_cuenta',
        'nombre_cuenta',
        'year',
        'month',
        'saldo_inicial',
        'debe',
        'haber',
        'saldo_final',
        'naturaleza',
        'estado',
        'id_empresa',
        'id_usuario_cierre',
        'fecha_cierre',
    ];

    protected $casts = [
        'saldo_inicial' => 'decimal:2',
        'debe' => 'decimal:2',
        'haber' => 'decimal:2',
        'saldo_final' => 'decimal:2',
        'fecha_cierre' => 'datetime',
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

    // Relaciones
    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class, 'id_cuenta');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa');
    }

    public function usuarioCierre()
    {
        return $this->belongsTo(User::class, 'id_usuario_cierre');
    }

    // Scope para filtrar por período
    public function scopePorPeriodo($query, $year, $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }

    // Scope para filtrar por estado
    public function scopeEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    // Calcular saldo según naturaleza
    public function calcularSaldoFinal()
    {
        if ($this->naturaleza == 'Deudor') {
            return $this->saldo_inicial + $this->debe - $this->haber;
        } else {
            return $this->saldo_inicial - $this->debe + $this->haber;
        }
    }

    // Verificar si el período está cerrado
    public function estaCerrado()
    {
        return $this->estado === 'Cerrado';
    }
}
