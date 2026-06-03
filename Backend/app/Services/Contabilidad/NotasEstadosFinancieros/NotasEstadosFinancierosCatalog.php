<?php

namespace App\Services\Contabilidad\NotasEstadosFinancieros;

class NotasEstadosFinancierosCatalog
{
    public const TIPO_AUTOMATICA = 'AUTOMATICA';

    public const TIPO_SEMI_AUTOMATICA = 'SEMI_AUTOMATICA';

    public const TIPO_MANUAL = 'MANUAL';

    public const ESTADO_COMPLETA = 'COMPLETA';

    public const ESTADO_PARCIAL = 'PARCIAL';

    public const ESTADO_PENDIENTE = 'PENDIENTE';

    /** @var array<int, array<string, mixed>> */
    public const DEFINICIONES = [
        1 => [
            'titulo' => 'Información general de la entidad',
            'tipo' => self::TIPO_SEMI_AUTOMATICA,
            'bloqueante_emision' => true,
        ],
        2 => [
            'titulo' => 'Declaración de cumplimiento NIIF para PYMES',
            'tipo' => self::TIPO_MANUAL,
            'bloqueante_emision' => true,
        ],
        3 => [
            'titulo' => 'Políticas contables significativas',
            'tipo' => self::TIPO_SEMI_AUTOMATICA,
            'bloqueante_emision' => true,
        ],
        4 => [
            'titulo' => 'Efectivo y equivalentes de efectivo',
            'tipo' => self::TIPO_AUTOMATICA,
            'bloqueante_emision' => false,
        ],
        5 => [
            'titulo' => 'Cuentas por cobrar y provisión para incobrables',
            'tipo' => self::TIPO_AUTOMATICA,
            'bloqueante_emision' => false,
        ],
        6 => [
            'titulo' => 'Inventarios',
            'tipo' => self::TIPO_AUTOMATICA,
            'bloqueante_emision' => false,
        ],
        7 => [
            'titulo' => 'Propiedad, planta y equipo',
            'tipo' => self::TIPO_AUTOMATICA,
            'bloqueante_emision' => false,
        ],
        8 => [
            'titulo' => 'Activos intangibles',
            'tipo' => self::TIPO_AUTOMATICA,
            'bloqueante_emision' => false,
        ],
        9 => [
            'titulo' => 'Préstamos bancarios y obligaciones financieras',
            'tipo' => self::TIPO_AUTOMATICA,
            'bloqueante_emision' => false,
        ],
        10 => [
            'titulo' => 'Conciliación del impuesto sobre la renta',
            'tipo' => self::TIPO_AUTOMATICA,
            'bloqueante_emision' => false,
        ],
        11 => [
            'titulo' => 'Beneficios a empleados',
            'tipo' => self::TIPO_AUTOMATICA,
            'bloqueante_emision' => false,
        ],
        12 => [
            'titulo' => 'Capital social y reserva legal',
            'tipo' => self::TIPO_AUTOMATICA,
            'bloqueante_emision' => false,
        ],
        13 => [
            'titulo' => 'Partes relacionadas',
            'tipo' => self::TIPO_SEMI_AUTOMATICA,
            'bloqueante_emision' => false,
        ],
        14 => [
            'titulo' => 'Contingencias y compromisos',
            'tipo' => self::TIPO_MANUAL,
            'bloqueante_emision' => false,
        ],
        15 => [
            'titulo' => 'Hechos posteriores al cierre',
            'tipo' => self::TIPO_MANUAL,
            'bloqueante_emision' => false,
        ],
        16 => [
            'titulo' => 'Información por segmentos',
            'tipo' => self::TIPO_MANUAL,
            'bloqueante_emision' => false,
        ],
    ];

    /** @return list<int> */
    public static function notasPorDefecto(): array
    {
        return range(1, 16);
    }

    public static function titulo(int $numero): string
    {
        return self::DEFINICIONES[$numero]['titulo'] ?? "Nota {$numero}";
    }

    public static function tipo(int $numero): string
    {
        return self::DEFINICIONES[$numero]['tipo'] ?? self::TIPO_MANUAL;
    }
}
