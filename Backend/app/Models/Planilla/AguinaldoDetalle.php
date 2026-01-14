<?php

namespace App\Models\Planilla;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AguinaldoDetalle extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'aguinaldo_detalles';

    protected $fillable = [
        'id_aguinaldo',
        'id_empleado',
        'monto_aguinaldo_bruto',
        'monto_exento',
        'monto_gravado',
        'retencion_renta',
        'aguinaldo_neto',
        'notas'
    ];

    protected $casts = [
        'monto_aguinaldo_bruto' => 'decimal:2',
        'monto_exento' => 'decimal:2',
        'monto_gravado' => 'decimal:2',
        'retencion_renta' => 'decimal:2',
        'aguinaldo_neto' => 'decimal:2'
    ];

    // Relaciones
    public function aguinaldo()
    {
        return $this->belongsTo(Aguinaldo::class, 'id_aguinaldo');
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'id_empleado');
    }

    // Métodos de cálculo
    /**
     * Calcula los valores de aguinaldo basándose en el monto bruto
     * Usa AguinaldoHelper para los cálculos
     */
    public function calcularValores()
    {
        if (!$this->monto_aguinaldo_bruto || $this->monto_aguinaldo_bruto <= 0) {
            return;
        }

        // Obtener tipo de contrato del empleado
        $tipoContrato = $this->empleado ? $this->empleado->tipo_contrato : null;
        $anio = $this->aguinaldo ? $this->aguinaldo->anio : date('Y');

        // Usar AguinaldoHelper para calcular
        $calculos = \App\Helpers\AguinaldoHelper::calcularDeduccionesAguinaldo(
            $this->monto_aguinaldo_bruto,
            $anio,
            $tipoContrato
        );

        // Asignar valores calculados
        $this->monto_exento = $calculos['monto_exento'];
        $this->monto_gravado = $calculos['monto_gravado'];
        $this->retencion_renta = $calculos['retencion_renta'];
        $this->aguinaldo_neto = $calculos['aguinaldo_neto'];
    }

    /**
     * Valida que los cálculos sean correctos
     */
    public function validarCalculos()
    {
        // Verificar que monto_bruto = monto_exento + monto_gravado
        $sumaExentoGravado = $this->monto_exento + $this->monto_gravado;
        $diferencia = abs($this->monto_aguinaldo_bruto - $sumaExentoGravado);

        if ($diferencia > 0.01) {
            return false;
        }

        // Verificar que aguinaldo_neto = monto_bruto - retencion_renta
        $netoCalculado = $this->monto_aguinaldo_bruto - $this->retencion_renta;
        $diferenciaNeto = abs($this->aguinaldo_neto - $netoCalculado);

        return $diferenciaNeto <= 0.01;
    }
}
