<?php

namespace App\Models\Planilla;

use App\Models\Planilla\Empleado;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanillaDetalle extends Model
{
    use HasFactory;

    protected $table = 'planilla_detalles';

    protected $fillable = [
        'id_planilla',
        'id_empleado',
        'salario_base',
        'salario_devengado',
        'dias_laborados',
        'horas_extra',
        'monto_horas_extra',
        'comisiones',
        'bonificaciones',
        'otros_ingresos',
        'isss_empleado',
        'isss_patronal',
        'afp_empleado',
        'afp_patronal',
        'renta',
        'prestamos',
        'anticipos',
        'otros_descuentos',
        'descuentos_judiciales',
        'detalle_otras_deducciones',
        'total_ingresos',
        'total_descuentos',
        'sueldo_neto',
        'estado'
    ];

    public function planilla()
    {
        return $this->belongsTo(Planilla::class, 'id_planilla');
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'id_empleado');
    }

    // Métodos de cálculo
    public function calcularSalarioDevengado()
    {
        $diasMes = 30; // Días estándar por mes
        return ($this->salario_base / $diasMes) * $this->dias_laborados;
    }

    public function calcularISSSAfp()
    {
        $baseCalculo = $this->total_ingresos;
        // Tope máximo ISSS = $1000
        $topeISSS = 1000;

        // ISSS = 3%
        $this->isss = min($baseCalculo, $topeISSS) * 0.03;

        // AFP = 7.25%
        $this->afp = $baseCalculo * 0.0725;
    }

    public function calcularISR()
    {
        $baseImponible = $this->total_ingresos - $this->isss - $this->afp;
        $baseAnual = $baseImponible * 12;

        // Cálculo según tabla de ISR de El Salvador
        if ($baseImponible <= 472.00) {
            $this->isr = 0;
        } elseif ($baseImponible <= 895.24) {
            $this->isr = (($baseImponible - 472.00) * 0.1) + 17.67;
        } elseif ($baseImponible <= 2038.10) {
            $this->isr = (($baseImponible - 895.24) * 0.2) + 60.00;
        } else {
            $this->isr = (($baseImponible - 2038.10) * 0.3) + 288.57;
        }
    }

    public function calcularTotales()
    {
        // Calcular total de ingresos
        $this->total_ingresos = $this->salario_devengado +
            $this->monto_horas_extra +
            $this->comisiones +
            $this->bonificaciones +
            $this->otros_ingresos;

        // Calcular deducciones (ISSS, AFP, ISR)
        $this->calcularISSSAfp();
        $this->calcularISR();

        // Calcular total de descuentos
        $this->total_descuentos = $this->isss +
            $this->afp +
            $this->isr +
            $this->otros_descuentos;

        // Calcular salario neto
        $this->salario_neto = $this->total_ingresos - $this->total_descuentos;
    }
}
