<?php

namespace App\Models;

use App\Constants\PlanillaConstants;
use App\Models\Admin\Empresa;
use App\Models\MH\Pais;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class EmpresaConfiguracionPlanilla extends Model
{
    use HasFactory;

    protected $table = 'empresa_configuracion_planilla';

    protected $fillable = [
        'empresa_id',
        'cod_pais',
        'configuracion',
        'activo',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta'
    ];

    protected $casts = [
        'configuracion' => 'array',
        'activo' => 'boolean',
        'fecha_vigencia_desde' => 'datetime',
        'fecha_vigencia_hasta' => 'datetime'
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function pais()
    {
        return $this->belongsTo(Pais::class, 'cod_pais', 'cod');
    }

    public function scopeActiva($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorEmpresa($query, $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopeVigente($query, $fecha = null)
    {
        $fecha = $fecha ?? now();

        return $query->where('fecha_vigencia_desde', '<=', $fecha)
            ->where(function ($q) use ($fecha) {
                $q->whereNull('fecha_vigencia_hasta')
                    ->orWhere('fecha_vigencia_hasta', '>=', $fecha);
            });
    }

    /**
     * Obtener configuración activa para una empresa
     */
    public static function obtenerConfiguracion($empresaId, $fecha = null)
    {
        return self::activa()
            ->porEmpresa($empresaId)
            ->vigente($fecha)
            ->latest('fecha_vigencia_desde')
            ->first();
    }

    /**
     * Obtener o crear configuración por defecto de El Salvador
     */
    public static function obtenerOCrearConfiguracion($empresaId)
    {
        $configuracion = self::obtenerConfiguracion($empresaId);

        Log::info('🔍 DEBUG HÍBRIDO', [
            'empresa_id2' => $empresaId,
            'configuracion2' => $configuracion
        ]);

        if (!$configuracion) {
            // Crear configuración por defecto
            $configuracion = self::create([
                'empresa_id' => $empresaId,
                'cod_pais' => 'SV', // Default El Salvador
                'configuracion' => self::getConfiguracionDefectoSV(),
                'activo' => true,
                'fecha_vigencia_desde' => now(),
                'fecha_vigencia_hasta' => null
            ]);
        }

        return $configuracion;
    }

    /**
     * Configuración por defecto de El Salvador
     */
    public static function getConfiguracionDefectoSV()
    {
        return [
            "conceptos" => [
                "isss_empleado" => [
                    "nombre" => "ISSS Empleado",
                    "codigo" => "ISSS_EMP",
                    "tipo" => "porcentaje",
                    "valor" => PlanillaConstants::DESCUENTO_ISSS_EMPLEADO * 100, // 3.0
                    "tope_maximo" => 1000,
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => true,
                    "es_patronal" => false,
                    "aplica_renta" => true,
                    "obligatorio" => true,
                    "orden" => 1
                ],
                "isss_patronal" => [
                    "nombre" => "ISSS Patronal",
                    "codigo" => "ISSS_PAT",
                    "tipo" => "porcentaje",
                    "valor" => PlanillaConstants::DESCUENTO_ISSS_PATRONO * 100, // 7.5
                    "tope_maximo" => 1000,
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => false,
                    "es_patronal" => true,
                    "aplica_renta" => false,
                    "obligatorio" => true,
                    "orden" => 2
                ],
                "afp_empleado" => [
                    "nombre" => "AFP Empleado",
                    "codigo" => "AFP_EMP",
                    "tipo" => "porcentaje",
                    "valor" => PlanillaConstants::DESCUENTO_AFP_EMPLEADO * 100, // 7.25
                    "tope_maximo" => null,
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => true,
                    "es_patronal" => false,
                    "aplica_renta" => true,
                    "obligatorio" => true,
                    "orden" => 3
                ],
                "afp_patronal" => [
                    "nombre" => "AFP Patronal",
                    "codigo" => "AFP_PAT",
                    "tipo" => "porcentaje",
                    "valor" => PlanillaConstants::DESCUENTO_AFP_PATRONO * 100, // 8.75
                    "tope_maximo" => null,
                    "base_calculo" => "salario_devengado",
                    "es_deduccion" => false,
                    "es_patronal" => true,
                    "aplica_renta" => false,
                    "obligatorio" => true,
                    "orden" => 4
                ],
                "renta" => [
                    "nombre" => "Retención Renta",
                    "codigo" => "RENTA",
                    "tipo" => "sistema_existente", // Usa RentaHelper
                    "base_calculo" => "salario_gravable",
                    "es_deduccion" => true,
                    "es_patronal" => false,
                    "aplica_renta" => false,
                    "obligatorio" => true,
                    "orden" => 5
                ],
                "horas_extra" => [
                    "nombre" => "Horas Extra",
                    "codigo" => "HORAS_EXTRA",
                    "tipo" => "porcentaje",
                    "valor" => PlanillaConstants::PORCENTAJE_HORAS_EXTRA * 100, // 25% recargo
                    "base_calculo" => "salario_hora",
                    "es_deduccion" => false,
                    "es_patronal" => false,
                    "aplica_renta" => true,
                    "obligatorio" => false,
                    "orden" => 6
                ]
            ],
            "configuraciones_generales" => [
                "moneda" => "USD",
                "dias_mes" => PlanillaConstants::DIAS_LABORADOS, // 30
                "horas_dia" => PlanillaConstants::HORAS_DIA, // 8
                "recargo_horas_extra" => PlanillaConstants::PORCENTAJE_HORAS_EXTRA * 100, // 25
                "frecuencia_pago_predeterminada" => "quincenal",
                "salario_minimo" => 365.00
            ]
        ];
    }

    // ==========================================
    // MÉTODOS DE CONFIGURACIÓN
    // ==========================================

    /**
     * Obtener conceptos configurados ordenados
     */
    public function getConceptos()
    {
        $conceptos = $this->configuracion['conceptos'] ?? [];

        // Ordenar por campo 'orden' si existe
        uasort($conceptos, function ($a, $b) {
            $ordenA = $a['orden'] ?? 999;
            $ordenB = $b['orden'] ?? 999;
            return $ordenA <=> $ordenB;
        });

        return $conceptos;
    }

    /**
     * Obtener concepto específico por código
     */
    public function getConcepto($codigo)
    {
        $conceptos = $this->configuracion['conceptos'] ?? [];
        return $conceptos[$codigo] ?? null;
    }

    /**
     * Obtener configuraciones generales
     */
    public function getConfiguracionesGenerales()
    {
        return $this->configuracion['configuraciones_generales'] ?? [
            'moneda' => 'USD',
            'dias_mes' => 30,
            'horas_dia' => 8,
            'recargo_horas_extra' => 25
        ];
    }

    /**
     * Verificar si un concepto existe
     */
    public function tieneConcepto($codigo)
    {
        return isset($this->configuracion['conceptos'][$codigo]);
    }

    /**
     * Obtener conceptos que son deducciones
     */
    public function getDeducciones()
    {
        $conceptos = $this->getConceptos();
        return array_filter($conceptos, function ($concepto) {
            return $concepto['es_deduccion'] ?? false;
        });
    }

    /**
     * Obtener conceptos que son ingresos
     */
    public function getIngresos()
    {
        $conceptos = $this->getConceptos();
        return array_filter($conceptos, function ($concepto) {
            return !($concepto['es_deduccion'] ?? false);
        });
    }

    /**
     * Obtener conceptos obligatorios
     */
    public function getConceptosObligatorios()
    {
        $conceptos = $this->getConceptos();
        return array_filter($conceptos, function ($concepto) {
            return $concepto['obligatorio'] ?? false;
        });
    }

    // ==========================================
    // MÉTODOS DE CÁLCULO RÁPIDO
    // ==========================================

    /**
     * Calcular ISSS de manera rápida
     */
    public function calcularISSS($salario)
    {
        $conceptoEmpleado = $this->getConcepto('isss_empleado');
        $conceptoPatronal = $this->getConcepto('isss_patronal');

        if (!$conceptoEmpleado || !$conceptoPatronal) {
            return ['empleado' => 0, 'patronal' => 0];
        }

        $tope = $conceptoEmpleado['tope_maximo'] ?? 1000;
        $base = min($salario, $tope);

        return [
            'empleado' => $base * ($conceptoEmpleado['valor'] / 100),
            'patronal' => $base * ($conceptoPatronal['valor'] / 100)
        ];
    }

    /**
     * Calcular AFP de manera rápida
     */
    public function calcularAFP($salario)
    {
        $conceptoEmpleado = $this->getConcepto('afp_empleado');
        $conceptoPatronal = $this->getConcepto('afp_patronal');

        if (!$conceptoEmpleado || !$conceptoPatronal) {
            return ['empleado' => 0, 'patronal' => 0];
        }

        return [
            'empleado' => $salario * ($conceptoEmpleado['valor'] / 100),
            'patronal' => $salario * ($conceptoPatronal['valor'] / 100)
        ];
    }

    /**
     * Calcular Renta usando tabla progresiva
     */
    public function calcularRenta($salarioGravable)
    {
        $conceptoRenta = $this->getConcepto('renta');

        if (!$conceptoRenta || $conceptoRenta['tipo'] !== 'tabla_progresiva') {
            return 0;
        }

        $tabla = $conceptoRenta['tabla'] ?? [];

        foreach ($tabla as $tramo) {
            $desde = $tramo['desde'];
            $hasta = $tramo['hasta'];

            if ($salarioGravable >= $desde && ($hasta === null || $salarioGravable <= $hasta)) {
                $exceso = $salarioGravable - $desde;
                $impuesto = ($exceso * ($tramo['porcentaje'] / 100)) + $tramo['cuota_fija'];
                return round($impuesto, 2);
            }
        }

        return 0;
    }

    // ==========================================
    // MÉTODOS DE ACTUALIZACIÓN
    // ==========================================

    /**
     * Agregar o actualizar concepto
     */
    public function actualizarConcepto($codigo, $concepto)
    {
        $configuracion = $this->configuracion;
        $configuracion['conceptos'][$codigo] = $concepto;

        $this->configuracion = $configuracion;
        $this->validarConfiguracion();

        return $this->save();
    }

    /**
     * Eliminar concepto
     */
    public function eliminarConcepto($codigo)
    {
        $configuracion = $this->configuracion;

        if (isset($configuracion['conceptos'][$codigo])) {
            unset($configuracion['conceptos'][$codigo]);
            $this->configuracion = $configuracion;
            return $this->save();
        }

        return false;
    }

    // ==========================================
    // MÉTODOS DE VALIDACIÓN
    // ==========================================

    /**
     * Validar que la configuración JSON sea válida
     */
    public function validarConfiguracion()
    {
        $configuracion = $this->configuracion;

        if (!isset($configuracion['conceptos'])) {
            throw new \Exception('La configuración debe tener una sección "conceptos"');
        }

        foreach ($configuracion['conceptos'] as $codigo => $concepto) {
            $this->validarConcepto($codigo, $concepto);
        }

        return true;
    }

    /**
     * Validar un concepto individual
     */
    private function validarConcepto($codigo, $concepto)
    {
        $camposRequeridos = ['nombre', 'tipo', 'base_calculo', 'es_deduccion'];
        
        foreach ($camposRequeridos as $campo) {
            if (!isset($concepto[$campo])) {
                throw new \Exception("El concepto '{$codigo}' debe tener el campo '{$campo}'");
            }
        }

        $tiposValidos = [
            'porcentaje', 
            'monto_fijo', 
            'tabla_progresiva', 
            'sistema_existente',
            'escala_antiguedad', 
            'dias_fijos'
        ];
        
        if (!in_array($concepto['tipo'], $tiposValidos)) {
            throw new \Exception("El concepto '{$codigo}' tiene un tipo inválido: {$concepto['tipo']}");
        }

        return true;
    }

    // ==========================================
    // COMPATIBILIDAD CON SISTEMA ACTUAL
    // ==========================================

    /**
     * Convertir configuración actual a formato antiguo (backward compatibility)
     */
    public function toFormatoAntiguo()
    {
        $conceptos = $this->getConceptos();

        return [
            'porcentaje_isss_empleado' => $conceptos['isss_empleado']['valor'] ?? 3.0,
            'porcentaje_isss_patronal' => $conceptos['isss_patronal']['valor'] ?? 7.5,
            'tope_isss' => $conceptos['isss_empleado']['tope_maximo'] ?? 1000,
            'porcentaje_afp_empleado' => $conceptos['afp_empleado']['valor'] ?? 7.25,
            'porcentaje_afp_patronal' => $conceptos['afp_patronal']['valor'] ?? 8.75,
        ];
    }
}
