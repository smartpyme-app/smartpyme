<?php

namespace App\Models\Planilla;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Planilla extends Model
{
    use HasFactory;

    protected $fillable = [
        'codigo',
        'fecha_inicio',
        'fecha_fin',
        'tipo_planilla', // quincenal, mensual
        'estado', // 1: borrador, 2: aprobada, 3: pagada
        'total_salarios',
        'total_deducciones',
        'total_neto',
        'total_viaticos',
        'id_empresa',
        'id_sucursal',
        'anio',
        'mes'
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'total_salarios' => 'decimal:2',
        'total_deducciones' => 'decimal:2',
        'total_neto' => 'decimal:2'
    ];

    // Relaciones
    public function detalles()
    {
        return $this->hasMany(PlanillaDetalle::class, 'id_planilla');
    }

    public function empresa()
    {
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    public function sucursal()
    {
        return $this->belongsTo('App\Models\Admin\Sucursal', 'id_sucursal');
    }

    // Scopes
    public function scopePeriodo($query, $anio, $mes)
    {
        return $query->where('anio', $anio)->where('mes', $mes);
    }

    public function scopeBorrador($query)
    {
        return $query->where('estado', 1);
    }

    public function scopeAprobada($query)
    {
        return $query->where('estado', 2);
    }

    public function scopePagada($query)
    {
        return $query->where('estado', 3);
    }

    // En el modelo Planilla.php

    public function actualizarTotales()
    {
        $detalles = $this->detalles;

        // Calcular totales
        $this->total_salarios = $detalles->sum(function ($detalle) {
            return $detalle->salario_devengado +
                $detalle->monto_horas_extra +
                $detalle->comisiones +
                $detalle->bonificaciones +
                $detalle->otros_ingresos;
        });

        // Total deducciones (incluye deducciones de ley y otras deducciones)
        $this->total_deducciones = $detalles->sum(function ($detalle) {
            return $detalle->isss_empleado +
                $detalle->afp_empleado +
                $detalle->renta +
                $detalle->prestamos +
                $detalle->anticipos +
                $detalle->otros_descuentos +
                $detalle->descuentos_judiciales;
        });

        // Total neto
        $this->total_neto = $this->total_salarios - $this->total_deducciones;

        // Total aportes patronales
        $this->total_aportes_patronales = $detalles->sum(function ($detalle) {
            return $detalle->isss_patronal + $detalle->afp_patronal;
        });

        $this->save();
    }
}
