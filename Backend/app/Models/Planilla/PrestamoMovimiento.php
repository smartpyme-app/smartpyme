<?php

namespace App\Models\Planilla;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrestamoMovimiento extends Model
{
    use HasFactory;

    protected $table = 'prestamo_movimientos';

    const TIPO_DESEMBOLSO = 'desembolso';
    const TIPO_ABONO_PLANILLA = 'abono_planilla';
    const TIPO_ABONO_EFECTIVO = 'abono_efectivo';

    protected $fillable = [
        'id_prestamo',
        'tipo',
        'monto',
        'saldo_despues',
        'descripcion',
        'fecha',
        'id_planilla_detalle',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'decimal:2',
        'saldo_despues' => 'decimal:2',
    ];

    // Relaciones
    public function prestamo()
    {
        return $this->belongsTo(PrestamoEmpleado::class, 'id_prestamo');
    }

    public function planillaDetalle()
    {
        return $this->belongsTo(PlanillaDetalle::class, 'id_planilla_detalle');
    }

    /**
     * Indica si el movimiento es un desembolso (aumenta la deuda).
     */
    public function esDesembolso(): bool
    {
        return $this->tipo === self::TIPO_DESEMBOLSO;
    }

    /**
     * Indica si el movimiento es un abono (reduce la deuda).
     */
    public function esAbono(): bool
    {
        return in_array($this->tipo, [self::TIPO_ABONO_PLANILLA, self::TIPO_ABONO_EFECTIVO], true);
    }
}
