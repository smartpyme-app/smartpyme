<?php

namespace App\Models;

use App\Models\Admin\Empresa;
use Illuminate\Database\Eloquent\Model;

class Suscripcion extends Model
{
    protected $table = 'suscripciones';

    protected $fillable = [
        'empresa_id',
        'plan_id',
        'usuario_id',
        'tipo_plan',
        'estado',
        'monto',
        'id_pago',
        'id_orden',
        'fecha_ultimo_pago',
        'fecha_proximo_pago',
        'fin_periodo_prueba',
        'fecha_cancelacion',
        'motivo_cancelacion'
    ];

    protected $dates = [
        'fecha_ultimo_pago',
        'fecha_proximo_pago',
        'fin_periodo_prueba',
        'fecha_cancelacion',
        'created_at',
        'updated_at'
    ];

    // Relaciones
    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    // Scopes
    public function scopeEmpresaActiva($query, $empresaId)
    {
        return $query->where('empresa_id', $empresaId)
                    ->where('estado', 'activo');
    }

    // Métodos
    public function estaActiva(): bool
    {
        return $this->estado === 'activo';
    }

    public function cancelar(?string $motivo = null): bool
    {
        return $this->update([
            'estado' => 'cancelado',
            'fecha_cancelacion' => now(),
            'motivo_cancelacion' => $motivo
        ]);
    }

    public function activar(): bool
    {
        return $this->update([
            'estado' => 'activo',
            'fecha_cancelacion' => null,
            'motivo_cancelacion' => null
        ]);
    }

}
