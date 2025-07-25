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
        'conceptos_personalizados',
        'pais_configuracion',
        'descuentos_judiciales',
        'detalle_otras_deducciones',
        'total_ingresos',
        'total_descuentos',
        'sueldo_neto',
        'estado'
    ];

    protected $casts = [
        'conceptos_personalizados' => 'array'
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
        $this->total_ingresos = $this->salario_devengado +
            $this->monto_horas_extra +
            $this->comisiones +
            $this->bonificaciones +
            $this->otros_ingresos;

        $this->total_descuentos = $this->getTotalDeduccionesCombinado();
        $this->sueldo_neto = $this->total_ingresos - $this->total_descuentos;
    }

    public function getConceptoPersonalizado($codigo)
    {
        $conceptos = $this->conceptos_personalizados ?? [];
        return $conceptos[$codigo] ?? null;
    }

    public function setConceptoPersonalizado($codigo, $valor, $tipo = 'deduccion')
    {
        $conceptos = $this->conceptos_personalizados ?? [];
        $conceptos[$codigo] = [
            'valor' => $valor,
            'tipo' => $tipo,
            'fecha_calculo' => now()->toISOString()
        ];
        $this->conceptos_personalizados = $conceptos;
    }

    public function getDeduccionesPersonalizadas()
    {
        $conceptos = $this->conceptos_personalizados ?? [];
        return array_filter($conceptos, function ($concepto) {
            return ($concepto['tipo'] ?? '') === 'deduccion';
        });
    }

    public function getAportesPatronalesPersonalizados()
    {
        $conceptos = $this->conceptos_personalizados ?? [];
        return array_filter($conceptos, function ($concepto) {
            return ($concepto['tipo'] ?? '') === 'aporte_patronal';
        });
    }

    public function getTotalDeduccionesPersonalizadas()
    {
        $deducciones = $this->getDeduccionesPersonalizadas();
        return array_sum(array_column($deducciones, 'valor'));
    }

    public function getTotalAportesPatronalesPersonalizados()
    {
        $aportes = $this->getAportesPatronalesPersonalizados();
        return array_sum(array_column($aportes, 'valor'));
    }

    public function usaConfiguracionPersonalizada()
    {
        return $this->pais_configuracion !== 'SV';
    }

    public function getTotalDeduccionesCombinado()
    {
        if ($this->usaConfiguracionPersonalizada()) {
            // Para países con configuración personalizada
            return $this->getTotalDeduccionesPersonalizadas() +
                $this->prestamos +
                $this->anticipos +
                $this->otros_descuentos +
                $this->descuentos_judiciales;
        } else {
            // Para El Salvador (campos fijos)
            return $this->isss_empleado +
                $this->afp_empleado +
                $this->renta +
                $this->prestamos +
                $this->anticipos +
                $this->otros_descuentos +
                $this->descuentos_judiciales;
        }
    }

    public function getIsssEmpleadoAttribute($value)
    {
        if ($this->usaConfiguracionPersonalizada()) {
            $igss = $this->getConceptoPersonalizado('IGSS_EMP') ?:
                $this->getConceptoPersonalizado('ISSS_EMP');
            return $igss['valor'] ?? 0;
        }
        return $value;
    }

    public function getAfpEmpleadoAttribute($value)
    {
        if ($this->usaConfiguracionPersonalizada()) {
            $concepto = $this->getConceptoPersonalizado('AFP_EMP');
            return $concepto['valor'] ?? 0;
        }
        return $value;
    }

    public function getRentaAttribute($value)
    {
        if ($this->usaConfiguracionPersonalizada()) {
            $conceptos = $this->conceptos_personalizados ?? [];

            // Buscar cualquier concepto de renta/impuesto
            foreach ($conceptos as $codigo => $concepto) {
                $codigoUpper = strtoupper($codigo);
                if (
                    str_contains($codigoUpper, 'ISR') ||
                    str_contains($codigoUpper, 'RENTA') ||
                    str_contains($codigoUpper, 'IR_') ||
                    ($concepto['tipo'] ?? '') === 'deduccion' &&
                    str_contains(strtoupper($concepto['nombre'] ?? ''), 'IMPUESTO')
                ) {
                    return $concepto['valor'] ?? 0;
                }
            }
            return 0;
        }
        return $value;
    }
}
