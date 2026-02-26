<?php

namespace App\Models\Planilla;

use App\Models\Admin\Empresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrestacionAdicional extends Model
{
    use HasFactory;

    protected $fillable = [
        'empleado_id',
        'empresa_id',
        'tipo',
        'fecha_inicio',
        'fecha_fin',
        'dias_calculados',
        'salario_base',
        'monto_prestacion',
        'prima_adicional',
        'isss',
        'afp',
        'renta',
        'otros_descuentos',
        'total_ingresos',
        'total_descuentos',
        'total_neto',
        'estado',
        'observaciones'
    ];

    protected $dates = ['fecha_inicio', 'fecha_fin'];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function calcularAguinaldo()
    {
        // Lógica para calcular aguinaldo según años de servicio
        $aniosServicio = $this->empleado->aniosServicio();
        
        if ($aniosServicio < 3) {
            $diasAguinaldo = 15;
        } elseif ($aniosServicio < 10) {
            $diasAguinaldo = 19;
        } else {
            $diasAguinaldo = 21;
        }

        $this->dias_calculados = $diasAguinaldo;
        $this->monto_prestacion = ($this->salario_base / 30) * $diasAguinaldo;
    }

    public function calcularVacaciones()
    {
        // 15 días de vacación + 30% de prima
        $this->dias_calculados = 15;
        $this->monto_prestacion = ($this->salario_base / 30) * 15;
        $this->prima_adicional = $this->monto_prestacion * 0.30;
    }

    public function calcularIndemnizacion()
    {
        // Un salario por año trabajado
        $aniosServicio = $this->empleado->aniosServicio();
        $this->monto_prestacion = $this->salario_base * $aniosServicio;
    }
}
