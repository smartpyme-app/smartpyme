<?php

namespace App\Models;

use App\Models\Admin\Empresa;
use App\Models\MH\Pais;
use Illuminate\Database\Eloquent\Model;

class EmpresaConfiguracion extends Model
{
    protected $table = 'empresa_configuracion';

    public const MODULO_PLANILLAS = 'planillas';
    public const MODULO_COLUMNAS = 'columnas';
    public const MODULO_MODULOS = 'modulos';
    public const MODULO_CONFIGURACIONES = 'configuraciones';
    public const MODULO_CAMPOS_PERSONALIZADOS = 'campos_personalizados';

    public const MODULOS_CUSTOM = [
        self::MODULO_COLUMNAS,
        self::MODULO_MODULOS,
        self::MODULO_CONFIGURACIONES,
        self::MODULO_CAMPOS_PERSONALIZADOS,
    ];

    protected $fillable = [
        'empresa_id',
        'pais',
        'modulo',
        'configuracion',
    ];

    protected $casts = [
        'configuracion' => 'array',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function paisRelacion()
    {
        return $this->belongsTo(Pais::class, 'pais', 'cod');
    }

    public function scopePorEmpresa($query, $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopeModulo($query, string $modulo)
    {
        return $query->where('modulo', $modulo);
    }

    public function getConceptos()
    {
        $conceptos = $this->configuracion['conceptos'] ?? [];

        uasort($conceptos, function ($a, $b) {
            return ($a['orden'] ?? 999) <=> ($b['orden'] ?? 999);
        });

        return $conceptos;
    }

    public function getConcepto($codigo)
    {
        return $this->configuracion['conceptos'][$codigo] ?? null;
    }

    public function getConfiguracionesGenerales()
    {
        return $this->configuracion['configuraciones_generales'] ?? [
            'moneda' => 'USD',
            'dias_mes' => 30,
            'horas_dia' => 8,
            'recargo_horas_extra' => 25,
        ];
    }

    public function getDeducciones()
    {
        return array_filter($this->getConceptos(), function ($concepto) {
            return $concepto['es_deduccion'] ?? false;
        });
    }

    public function getIngresos()
    {
        return array_filter($this->getConceptos(), function ($concepto) {
            return !($concepto['es_deduccion'] ?? false);
        });
    }

    public function validarConfiguracion()
    {
        $configuracion = $this->configuracion;

        if (!isset($configuracion['conceptos'])) {
            throw new \Exception('La configuración debe tener una sección "conceptos"');
        }

        $tiposValidos = [
            'porcentaje',
            'monto_fijo',
            'tabla_progresiva',
            'sistema_existente',
            'escala_antiguedad',
            'dias_fijos',
        ];

        foreach ($configuracion['conceptos'] as $codigo => $concepto) {
            foreach (['nombre', 'tipo', 'base_calculo', 'es_deduccion'] as $campo) {
                if (!isset($concepto[$campo])) {
                    throw new \Exception("El concepto '{$codigo}' debe tener el campo '{$campo}'");
                }
            }
            if (!in_array($concepto['tipo'], $tiposValidos, true)) {
                throw new \Exception("El concepto '{$codigo}' tiene un tipo inválido: {$concepto['tipo']}");
            }
        }

        return true;
    }
}
