<?php

namespace App\Models\Planilla;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrestamoEmpleado extends Model
{
    use HasFactory;

    protected $table = 'prestamos_empleados';

    const ESTADO_ACTIVO = 'activo';
    const ESTADO_LIQUIDADO = 'liquidado';
    const ESTADO_CANCELADO = 'cancelado';

    protected $fillable = [
        'id_empleado',
        'id_empresa',
        'numero_prestamo',
        'monto_inicial',
        'saldo_actual',
        'descripcion',
        'fecha_desembolso',
        'estado',
    ];

    protected $casts = [
        'fecha_desembolso' => 'date',
        'monto_inicial' => 'decimal:2',
        'saldo_actual' => 'decimal:2',
    ];

    // Relaciones
    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'id_empleado');
    }

    public function empresa()
    {
        return $this->belongsTo(\App\Models\Admin\Empresa::class, 'id_empresa');
    }

    public function movimientos()
    {
        return $this->hasMany(PrestamoMovimiento::class, 'id_prestamo')->orderBy('fecha')->orderBy('id');
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('estado', self::ESTADO_ACTIVO);
    }

    public function scopePorEmpleado($query, $idEmpleado)
    {
        return $query->where('id_empleado', $idEmpleado);
    }

    public function scopePorEmpresa($query, $idEmpresa)
    {
        return $query->where('id_empresa', $idEmpresa);
    }

    /**
     * Indica si el préstamo tiene saldo pendiente.
     */
    public function tieneSaldoPendiente(): bool
    {
        return (float) $this->saldo_actual > 0;
    }

    /**
     * Obtiene el siguiente número de préstamo para un empleado.
     */
    public static function siguienteNumeroParaEmpleado(int $idEmpleado): int
    {
        $max = static::where('id_empleado', $idEmpleado)->max('numero_prestamo');
        return ($max ?? 0) + 1;
    }
}
