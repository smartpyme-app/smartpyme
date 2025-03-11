<?php

namespace App\Models;

use App\Models\Admin\Empresa;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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
        'metodo_pago',  // 1: n1co, 2: transferencia
        'estado_ultimo_pago',
        'fecha_ultimo_pago',
        'fecha_proximo_pago',
        'fin_periodo_prueba',
        'fecha_cancelacion',
        'motivo_cancelacion',
        'nit',
        'nombre_factura',
        'direccion_factura',
        'intentos_cobro',
        'ultimo_intento_cobro',
        'historial_pagos'
    ];

    protected $dates = [
        'fecha_ultimo_pago',
        'fecha_proximo_pago',
        'fin_periodo_prueba',
        'fecha_cancelacion',
        'ultimo_intento_cobro',
        'created_at',
        'updated_at'
    ];

    // Relaciones
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function ordenesPago()
    {
        return $this->hasMany(OrdenPago::class, 'payment_id', 'id_pago');
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

    private function calcularDiasFaltantes(): int
    {
        $fechaActual = now();
        $fechaProximoPago = Carbon::parse($this->fecha_proximo_pago);
        
        // Si es el mismo día, verificar la hora
        if ($fechaActual->isSameDay($fechaProximoPago)) {
            // Si aún no ha pasado la hora límite, considerar que falta 0 días (mismo día)
            if ($fechaActual < $fechaProximoPago) {
                return 0;
            } else {
                // Si ya pasó la hora límite, considerar vencido recién
                return 0;
            }
        }
        
        // Si es un día diferente
        if ($fechaActual > $fechaProximoPago) {
            // Si está vencida, retorna días negativos
            return -$fechaActual->diffInDays($fechaProximoPago);
        }
        
        return $fechaActual->diffInDays($fechaProximoPago);
    }

    public function calcularDiasFaltantesPrueba(): int
    {
        $fechaActual = now();
        $fechaFinPrueba = Carbon::parse($this->fin_periodo_prueba);
        
        // Si es el mismo día, verificar la hora
        if ($fechaActual->isSameDay($fechaFinPrueba)) {
            // Si aún no ha pasado la hora límite, considerar que falta 0 días (mismo día)
            if ($fechaActual < $fechaFinPrueba) {
                return 0;
            } else {
                // Si ya pasó la hora límite, considerar vencido recién
                return 0;
            }
        }
        
        // Si es un día diferente
        if ($fechaActual > $fechaFinPrueba) {
            return -$fechaActual->diffInDays($fechaFinPrueba);
        }
        
        return $fechaActual->diffInDays($fechaFinPrueba);
    }

    public function diasFaltantes(): ?int 

    {
        if (!$this->exists) {
            return null;
        }
        
        $diasFaltantes = $this->calcularDiasFaltantes();
        return $diasFaltantes;
    }

    public function diasFaltantesPrueba(): ?int
    {
        if (!$this->exists) {
            return null;
        }   
        
        $diasFaltantes = $this->calcularDiasFaltantesPrueba();
        return $diasFaltantes;
    }
    


}
