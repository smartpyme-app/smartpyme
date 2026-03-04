<?php

namespace App\Models;

use App\Models\Admin\Empresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionPrestacion extends Model
{
    use HasFactory;

    protected $table = 'configuracion_prestaciones';

    protected $fillable = [
        'empresa_id',
        'porcentaje_isss_empleado',
        'porcentaje_isss_patronal',
        'tope_isss',
        'porcentaje_afp_empleado',
        'porcentaje_afp_patronal',
        'dias_aguinaldo_1_3',
        'dias_aguinaldo_3_10',
        'dias_aguinaldo_10_mas',
        'dias_vacaciones',
        'porcentaje_prima_vacacional',
        'fecha_inicio_vigencia',
        'fecha_fin_vigencia',
        'activo'
    ];

    protected $dates = [
        'fecha_inicio_vigencia',
        'fecha_fin_vigencia'
    ];

    // Relaciones
    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    // Scopes
    public function scopeActiva($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorEmpresa($query, $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    // Métodos para obtener configuración
    public static function obtenerConfiguracionActiva($empresaId)
    {
        return self::activa()
                   ->porEmpresa($empresaId)
                   ->latest()
                   ->first();
    }

    // Métodos de cálculo
    public function calcularISSS($salario)
    {
        $base = min($salario, $this->tope_isss);
        return [
            'empleado' => $base * ($this->porcentaje_isss_empleado / 100),
            'patronal' => $base * ($this->porcentaje_isss_patronal / 100)
        ];
    }

    public function calcularAFP($salario)
    {
        return [
            'empleado' => $salario * ($this->porcentaje_afp_empleado / 100),
            'patronal' => $salario * ($this->porcentaje_afp_patronal / 100)
        ];
    }

    public function obtenerDiasAguinaldo($aniosServicio)
    {
        if ($aniosServicio < 3) {
            return $this->dias_aguinaldo_1_3;
        } elseif ($aniosServicio < 10) {
            return $this->dias_aguinaldo_3_10;
        } else {
            return $this->dias_aguinaldo_10_mas;
        }
    }

    public function calcularAguinaldo($salario, $aniosServicio)
    {
        $dias = $this->obtenerDiasAguinaldo($aniosServicio);
        return ($salario / 30) * $dias;
    }

    public function calcularVacaciones($salario)
    {
        $baseVacaciones = ($salario / 30) * $this->dias_vacaciones;
        $prima = $baseVacaciones * ($this->porcentaje_prima_vacacional / 100);
        
        return [
            'base' => $baseVacaciones,
            'prima' => $prima,
            'total' => $baseVacaciones + $prima
        ];
    }
}
