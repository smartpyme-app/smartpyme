<?php

namespace App\Services\Contabilidad;

use Carbon\Carbon;

/**
 * Estado de flujo de efectivo híbrido (indirecto + conciliación de efectivo), v1.
 * Ver docs/contabilidad/especificacion-flujo-efectivo-v1-hibrido.md
 */
class FlujoEfectivoHibridoNiifSvPresenter
{
    /** @var list<string> */
    private const OPERATING_ASSET_KEYS = [
        'cuentas_cobrar_clientes',
        'documentos_cobrar',
        'provision_incobrables',
        'inventarios',
        'iva_credito_fiscal',
        'pago_cuenta_acumulado',
        'gastos_anticipados',
        'otros_activos_corrientes',
    ];

    /** @var list<string> */
    private const OPERATING_LIABILITY_KEYS = [
        'cuentas_pagar_proveedores',
        'prestamos_corto_plazo',
        'iva_debito_fiscal',
        'isr_por_pagar',
        'afp_por_pagar',
        'isss_por_pagar',
        'retenciones_isr_empleados',
        'otras_cuentas_pagar_corrientes',
    ];

    /** @var list<string> */
    private const INVESTING_KEYS = [
        'propiedad_planta_equipo',
        'activos_intangibles',
        'inversiones_largo_plazo',
        'activos_impuesto_diferido',
        'otros_activos_no_corrientes',
    ];

    /**
     * Excluye utilidad_ejercicio en v1 para reducir riesgo de doble conteo con la utilidad del ER.
     *
     * @var list<string>
     */
    private const FINANCING_KEYS = [
        'prestamos_largo_plazo',
        'provision_indemnizaciones',
        'pasivos_impuesto_diferido',
        'otros_pasivos_no_corrientes',
        'capital_social',
        'reserva_legal',
        'utilidades_retenidas',
        'superavit_revaluacion',
    ];

    public function __construct(
        private BalanceGeneralNiifSvPresenter $balance,
        private EstadoResultadosNiifSvPresenter $estadoResultados,
    ) {}

    /**
     * Rango de fechas del “snapshot” de balance NIIF inmediatamente anterior al inicio del periodo
     * (misma convención que la especificación: cierre al día anterior a fecha_inicio).
     *
     * @return array{0: Carbon, 1: Carbon} [$openStart, $openEnd]
     */
    public static function openingSnapshotRange(Carbon $fechaInicio): array
    {
        $fi = $fechaInicio->copy()->startOfDay();

        if ($fi->day === 1) {
            $openEnd = (clone $fi)->subDay()->endOfDay();
            $openStart = (clone $fi)->subMonth()->startOfMonth()->startOfDay();
        } else {
            $openStart = $fi->copy()->startOfMonth()->startOfDay();
            $openEnd = $fi->copy()->subDay()->endOfDay();
        }

        return [$openStart, $openEnd];
    }

    /**
     * @return array<string, mixed>
     */
    public function build(int $empresaId, Carbon $fechaInicio, Carbon $fechaFin, bool $comparar = false): array
    {
        $fi = $fechaInicio->copy()->startOfDay();
        $ff = $fechaFin->copy()->endOfDay();

        $actual = $this->computeOnePeriod($empresaId, $fi, $ff);

        $out = [
            'moneda' => 'USD',
            'mostrar_comparativa' => $comparar,
            'periodo_actual' => [
                'fecha_inicio' => $fi->toDateString(),
                'fecha_fin' => $ff->toDateString(),
                'titulo' => $this->formatPeriodoTitulo($fi, $ff),
            ],
            'actual' => $actual,
        ];

        if ($comparar) {
            [$ps, $pe] = EstadoResultadosNiifSvPresenter::periodoAnterior($fi, $ff);
            $out['periodo_anterior'] = [
                'fecha_inicio' => $ps->toDateString(),
                'fecha_fin' => $pe->toDateString(),
                'titulo' => $this->formatPeriodoTitulo($ps, $pe),
            ];
            $out['anterior'] = $this->computeOnePeriod($empresaId, $ps, $pe);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function computeOnePeriod(int $empresaId, Carbon $fi, Carbon $ff): array
    {
        [$openStart, $openEnd] = self::openingSnapshotRange($fi);

        $snapStart = $this->balance->rawLines($empresaId, $openStart, $openEnd);
        $snapEnd = $this->balance->rawLines($empresaId, $fi, $ff);

        $L0 = $snapStart['lines'];
        $L1 = $snapEnd['lines'];

        $er = $this->estadoResultados->build($empresaId, $fi, $ff);
        $utilNeta = (float) ($er['cascada'][EstadoResultadosNiifSvPresenter::LBL_UTIL_NETA] ?? 0);
        $deprec = (float) ($er['L']['gasto_venta_deprec'] ?? 0)
            + (float) ($er['L']['gasto_admin_deprec'] ?? 0);

        $operatingDetail = [];
        $operating = $utilNeta + $deprec;

        $operatingDetail[] = [
            'clave' => 'utilidad_neta_er',
            'etiqueta' => 'Utilidad neta del ejercicio (estimada) — ref. estado de resultados',
            'monto' => $utilNeta,
        ];
        $operatingDetail[] = [
            'clave' => 'depreciacion_amortizacion',
            'etiqueta' => 'Depreciación y amortización (reintegro, no efectivo)',
            'monto' => $deprec,
        ];

        foreach (self::OPERATING_ASSET_KEYS as $key) {
            $d = ($L1[$key] ?? 0) - ($L0[$key] ?? 0);
            $effect = -1.0 * $d;
            $operating += $effect;
            $operatingDetail[] = [
                'clave' => 'wc_' . $key,
                'etiqueta' => 'Variación ' . $key . ' (activo corriente)',
                'delta_linea' => $d,
                'monto' => $effect,
            ];
        }
        foreach (self::OPERATING_LIABILITY_KEYS as $key) {
            $d = ($L1[$key] ?? 0) - ($L0[$key] ?? 0);
            $effect = 1.0 * $d;
            $operating += $effect;
            $operatingDetail[] = [
                'clave' => 'wc_' . $key,
                'etiqueta' => 'Variación ' . $key . ' (pasivo corriente)',
                'delta_linea' => $d,
                'monto' => $effect,
            ];
        }

        $investingDetail = [];
        $investing = 0.0;
        foreach (self::INVESTING_KEYS as $key) {
            $d = ($L1[$key] ?? 0) - ($L0[$key] ?? 0);
            $effect = -1.0 * $d;
            $investing += $effect;
            $investingDetail[] = [
                'clave' => 'inv_' . $key,
                'etiqueta' => 'Variación ' . $key,
                'delta_linea' => $d,
                'monto' => $effect,
            ];
        }

        $financingDetail = [];
        $financing = 0.0;
        foreach (self::FINANCING_KEYS as $key) {
            $d = ($L1[$key] ?? 0) - ($L0[$key] ?? 0);
            $effect = 1.0 * $d;
            $financing += $effect;
            $financingDetail[] = [
                'clave' => 'fin_' . $key,
                'etiqueta' => 'Variación ' . $key,
                'delta_linea' => $d,
                'monto' => $effect,
            ];
        }

        $deltaEfectivoLinea = ($L1['efectivo_equivalentes'] ?? 0) - ($L0['efectivo_equivalentes'] ?? 0);
        $flujoIndirectoNeto = $operating + $investing + $financing;
        $diferenciaConciliacion = $flujoIndirectoNeto - $deltaEfectivoLinea;

        return [
            'snapshots' => [
                'apertura_rango' => [
                    'fecha_inicio' => $openStart->toDateString(),
                    'fecha_fin' => $openEnd->toDateString(),
                ],
                'cierre_rango' => [
                    'fecha_inicio' => $fi->toDateString(),
                    'fecha_fin' => $ff->toDateString(),
                ],
            ],
            'operacion' => [
                'detalle' => $operatingDetail,
                'total' => $operating,
            ],
            'inversion' => [
                'detalle' => $investingDetail,
                'total' => $investing,
            ],
            'financiacion' => [
                'detalle' => $financingDetail,
                'total' => $financing,
            ],
            'efectivo' => [
                'saldo_linea_inicio' => (float) ($L0['efectivo_equivalentes'] ?? 0),
                'saldo_linea_fin' => (float) ($L1['efectivo_equivalentes'] ?? 0),
                'variacion_linea' => $deltaEfectivoLinea,
            ],
            'conciliacion' => [
                'flujo_indirecto_neto' => $flujoIndirectoNeto,
                'variacion_efectivo_balance' => $deltaEfectivoLinea,
                'diferencia' => $diferenciaConciliacion,
            ],
            'meta' => [
                'nota_utilidad_ejercicio_excluida' => 'La variación de la cuenta patrimonial utilidad_ejercicio no se incluye en financiación (v1) para alinear con el punto de partida del ER.',
            ],
        ];
    }

    private function formatPeriodoTitulo(Carbon $start, Carbon $end): string
    {
        if ($start->isSameDay($end)) {
            return 'Al ' . $end->translatedFormat('d \\d\\e F \\d\\e Y');
        }

        return 'Del ' . $start->translatedFormat('d M Y') . ' al ' . $end->translatedFormat('d M Y');
    }
}
