<?php

namespace App\Services\Contabilidad\NotasEstadosFinancieros;

use App\Models\Admin\Empresa;
use App\Models\Bancos\Cuenta as CuentaBancaria;
use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\NotasEstadosFinancieros;
use App\Models\Planilla\PlanillaDetalle;
use App\Models\Ventas\Venta;
use App\Services\Contabilidad\BalanceGeneralNiifSvPresenter;
use App\Services\Contabilidad\CambiosPatrimonioNiifSvPresenter;
use App\Services\Contabilidad\EstadoResultadosNiifSvPresenter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NotasEstadosFinancierosService
{
    public function __construct(
        private BalanceGeneralNiifSvPresenter $balance,
        private EstadoResultadosNiifSvPresenter $estadoResultados,
        private CambiosPatrimonioNiifSvPresenter $cambiosPatrimonio,
        private NotasEstadosFinancierosValidacionService $validacion,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function generar(array $params, ?NotasEstadosFinancieros $existente = null): array
    {
        $empresaId = (int) ($params['empresa_id'] ?? Auth::user()->id_empresa);
        $fechaInicio = Carbon::parse($params['fecha_inicio'])->startOfDay();
        $fechaFin = Carbon::parse($params['fecha_fin'])->endOfDay();
        $notasIncluir = array_map('intval', $params['notas_a_incluir'] ?? NotasEstadosFinancierosCatalog::notasPorDefecto());
        $nivelDetalle = ($params['nivel_detalle'] ?? 'completo') === 'resumido' ? 'resumido' : 'completo';
        $config = (array) ($params['configuracion'] ?? []);
        $manual = (array) ($params['contenido_manual'] ?? ($existente?->contenido_manual ?? []));

        $balance = $this->balance->build($empresaId, $fechaInicio, $fechaFin);
        $balanceRaw = $this->balance->rawLines($empresaId, $fechaInicio, $fechaFin);
        $er = $this->estadoResultados->build($empresaId, $fechaInicio, $fechaFin);
        $empresa = Empresa::findOrFail($empresaId);
        $contabilidadConfig = Configuracion::withoutGlobalScopes()->where('id_empresa', $empresaId)->first();

        $contexto = [
            'empresa' => $empresa,
            'empresa_id' => $empresaId,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'fecha_aprobacion_junta' => $params['fecha_aprobacion_junta'] ?? null,
            'periodo_actual' => $params['periodo_actual'] ?? $fechaFin->format('Y'),
            'incluir_comparativo' => (bool) ($params['incluir_comparativo'] ?? false),
            'periodo_anterior' => $params['periodo_anterior'] ?? null,
            'nivel_detalle' => $nivelDetalle,
            'configuracion' => $config,
            'contenido_manual' => $manual,
            'balance' => $balance,
            'balance_lines' => $balanceRaw['lines'],
            'estado_resultados' => $er,
            'contabilidad_config' => $contabilidadConfig,
        ];

        $notas = [];
        foreach ($notasIncluir as $numero) {
            if (! isset(NotasEstadosFinancierosCatalog::DEFINICIONES[$numero])) {
                continue;
            }
            $notas[$numero] = $this->generarNota($numero, $contexto);
        }

        $validaciones = $this->validacion->validar($notas, $balanceRaw['lines'], $er);
        $completitud = $this->calcularCompletitud($notas, $notasIncluir);

        $payload = [
            'empresa_id' => $empresaId,
            'empresa' => ['nombre' => $empresa->nombre, 'nit' => $empresa->nit],
            'periodo_actual' => $contexto['periodo_actual'],
            'fecha_inicio' => $fechaInicio->toDateString(),
            'fecha_fin' => $fechaFin->toDateString(),
            'fecha_aprobacion_junta' => $contexto['fecha_aprobacion_junta'],
            'incluir_comparativo' => $contexto['incluir_comparativo'],
            'periodo_anterior' => $contexto['periodo_anterior'],
            'nivel_detalle' => $nivelDetalle,
            'notas_a_incluir' => $notasIncluir,
            'notas' => $notas,
            'validaciones_cruzadas' => $validaciones,
            'completitud' => $completitud,
            'puede_emitir' => $completitud['puede_emitir'],
        ];

        if ($existente) {
            $existente->fill([
                'notas_generadas' => $notas,
                'completitud' => $completitud,
                'validaciones_cruzadas' => $validaciones,
                'contenido_manual' => $manual,
                'configuracion' => $config,
            ]);
            $existente->save();
            $payload['id'] = $existente->id;
            $payload['estado'] = $existente->estado;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $contexto
     * @return array<string, mixed>
     */
    private function generarNota(int $numero, array $contexto): array
    {
        $def = NotasEstadosFinancierosCatalog::DEFINICIONES[$numero];
        $datos = match ($numero) {
            1 => $this->nota01($contexto),
            2 => $this->nota02($contexto),
            3 => $this->nota03($contexto),
            4 => $this->nota04($contexto),
            5 => $this->nota05($contexto),
            6 => $this->nota06($contexto),
            7 => $this->nota07($contexto),
            8 => $this->nota08($contexto),
            9 => $this->nota09($contexto),
            10 => $this->nota10($contexto),
            11 => $this->nota11($contexto),
            12 => $this->nota12($contexto),
            13 => $this->nota13($contexto),
            14 => $this->nota14($contexto),
            15 => $this->nota15($contexto),
            16 => $this->nota16($contexto),
            default => [],
        };

        return array_merge([
            'numero' => $numero,
            'titulo' => $def['titulo'],
            'tipo' => $def['tipo'],
            'estado' => $datos['estado'] ?? NotasEstadosFinancierosCatalog::ESTADO_PENDIENTE,
        ], $datos);
    }

    /** @param  array<string, mixed>  $ctx */
    private function nota01(array $ctx): array
    {
        /** @var Empresa $e */
        $e = $ctx['empresa'];
        $manual = (array) ($ctx['contenido_manual'][1] ?? []);
        $datos = [
            'nombre_legal' => $e->nombre,
            'nit' => $e->nit,
            'ncr' => $e->ncr,
            'giro' => $e->giro,
            'sector' => $e->sector,
            'tipo_contribuyente' => $e->tipo_contribuyente,
            'domicilio' => trim(implode(', ', array_filter([$e->direccion, $e->distrito, $e->municipio, $e->departamento]))),
            'telefono' => $e->telefono,
            'correo' => $e->correo,
            'actividad_economica' => $e->cod_actividad_economica,
            'descripcion_actividad' => $manual['descripcion_actividad'] ?? $e->giro,
            'estructura_societaria' => $manual['estructura_societaria'] ?? '',
        ];

        $faltantes = array_filter([
            empty($datos['nombre_legal']) ? 'nombre_legal' : null,
            empty($datos['nit']) ? 'nit' : null,
            empty($datos['descripcion_actividad']) ? 'descripcion_actividad' : null,
        ]);

        return [
            'contenido' => $datos,
            'estado' => empty($faltantes) ? NotasEstadosFinancierosCatalog::ESTADO_COMPLETA : NotasEstadosFinancierosCatalog::ESTADO_PARCIAL,
            'campos_pendientes' => array_values($faltantes),
        ];
    }

    /** @param  array<string, mixed>  $ctx */
    private function nota02(array $ctx): array
    {
        /** @var Empresa $e */
        $e = $ctx['empresa'];
        $manual = (array) ($ctx['contenido_manual'][2] ?? []);
        $plantilla = 'Los estados financieros de {empresa} correspondientes al periodo del {fecha_inicio} al {fecha_fin} '
            . 'han sido preparados de conformidad con la NIIF para PYMES, según lo aprobado por el Consejo Vocacional '
            . 'de Contadores Públicos y Auditores de El Salvador (CVPCPA). '
            . 'La junta directiva aprobó estos estados financieros el {fecha_aprobacion}.';

        $texto = $manual['texto'] ?? strtr($plantilla, [
            '{empresa}' => $e->nombre,
            '{fecha_inicio}' => $ctx['fecha_inicio']->format('d/m/Y'),
            '{fecha_fin}' => $ctx['fecha_fin']->format('d/m/Y'),
            '{fecha_aprobacion}' => $ctx['fecha_aprobacion_junta']
                ? Carbon::parse($ctx['fecha_aprobacion_junta'])->format('d/m/Y')
                : '[fecha de aprobación pendiente]',
        ]);

        $completa = ! empty(trim($texto)) && ! empty($ctx['fecha_aprobacion_junta']);

        return [
            'contenido' => ['texto' => $texto],
            'estado' => $completa
                ? NotasEstadosFinancierosCatalog::ESTADO_COMPLETA
                : (trim($texto) !== '' ? NotasEstadosFinancierosCatalog::ESTADO_PARCIAL : NotasEstadosFinancierosCatalog::ESTADO_PENDIENTE),
        ];
    }

    /** @param  array<string, mixed>  $ctx */
    private function nota03(array $ctx): array
    {
        $cfg = $ctx['contabilidad_config'];
        $manual = (array) ($ctx['contenido_manual'][3] ?? []);
        $politicas = [
            'moneda_funcional' => 'Dólares estadounidenses (USD)',
            'inventarios' => 'PEPS (FIFO) conforme NIC 2, cuando aplica',
            'depreciacion' => 'Línea recta sobre vida útil estimada de activos',
            'reconocimiento_ingresos' => 'Al devengarse la prestación del servicio o entrega del bien',
            'generacion_partidas' => $cfg?->generar_partidas ?? 'Manual',
            'politicas_adicionales' => $manual['politicas_adicionales'] ?? '',
        ];

        $estado = ($cfg && $politicas['generacion_partidas'])
            ? NotasEstadosFinancierosCatalog::ESTADO_COMPLETA
            : NotasEstadosFinancierosCatalog::ESTADO_PARCIAL;

        return ['contenido' => $politicas, 'estado' => $estado];
    }

    /** @param  array<string, mixed>  $ctx */
    private function nota04(array $ctx): array
    {
        $lines = $ctx['balance_lines'];
        $cuentas = CuentaBancaria::withoutGlobalScopes()
            ->where('id_empresa', $ctx['empresa_id'])
            ->orderBy('nombre_banco')
            ->get(['id', 'nombre_banco', 'numero', 'tipo', 'saldo']);

        $detalle = $cuentas->map(fn ($c) => [
            'banco' => $c->nombre_banco,
            'numero' => $c->numero,
            'tipo' => $c->tipo,
            'saldo_modulo_bancos' => (float) $c->saldo,
        ])->values()->all();

        $totalModulo = array_sum(array_column($detalle, 'saldo_modulo_bancos'));
        $totalBalance = (float) ($lines['efectivo_equivalentes'] ?? 0);

        return [
            'contenido' => [
                'total_efectivo' => $totalBalance,
                'total_modulo_bancos' => round($totalModulo, 2),
                'cuentas' => $ctx['nivel_detalle'] === 'resumido' ? [] : $detalle,
            ],
            'estado' => NotasEstadosFinancierosCatalog::ESTADO_COMPLETA,
        ];
    }

    /** @param  array<string, mixed>  $ctx */
    private function nota05(array $ctx): array
    {
        $lines = $ctx['balance_lines'];
        $pctProvision = (float) ($ctx['configuracion']['provision_incobrables_pct'] ?? 2.0);
        $fechaCorte = $ctx['fecha_fin'];

        $ventas = Venta::withoutGlobalScopes()
            ->where('id_empresa', $ctx['empresa_id'])
            ->whereIn('estado', ['Pagada', 'Pendiente', 'Consigna'])
            ->where('fecha', '<=', $fechaCorte->toDateString())
            ->withSum(['abonos as abonos_confirmados' => fn ($q) => $q->where('estado', 'Confirmado')], 'total')
            ->get(['id', 'fecha', 'total', 'condicion', 'correlativo']);

        $buckets = ['0_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, '91_mas' => 0.0];
        foreach ($ventas as $v) {
            $saldo = round((float) $v->total - (float) ($v->abonos_confirmados ?? 0), 2);
            if ($saldo <= 0.009) {
                continue;
            }
            $dias = $v->fecha ? Carbon::parse($v->fecha)->diffInDays($fechaCorte) : 0;
            if ($dias <= 30) {
                $buckets['0_30'] += $saldo;
            } elseif ($dias <= 60) {
                $buckets['31_60'] += $saldo;
            } elseif ($dias <= 90) {
                $buckets['61_90'] += $saldo;
            } else {
                $buckets['91_mas'] += $saldo;
            }
        }

        $bruto = (float) ($lines['cuentas_cobrar_clientes'] ?? 0)
            + (float) ($lines['documentos_cobrar'] ?? 0);
        $provisionBalance = abs((float) ($lines['provision_incobrables'] ?? 0));
        $provisionCalc = round($bruto * ($pctProvision / 100), 2);

        return [
            'contenido' => [
                'cuentas_por_cobrar_bruto' => $bruto,
                'provision_balance' => $provisionBalance,
                'provision_pct_configurada' => $pctProvision,
                'provision_calculada' => $provisionCalc,
                'antiguedad_saldos' => $buckets,
                'neto' => $bruto - $provisionBalance,
            ],
            'estado' => NotasEstadosFinancierosCatalog::ESTADO_COMPLETA,
        ];
    }

    /** @param  array<string, mixed>  $ctx */
    private function nota06(array $ctx): array
    {
        $lines = $ctx['balance_lines'];
        $totalBalance = (float) ($lines['inventarios'] ?? 0);

        $valorModulo = (float) DB::table('inventario')
            ->join('productos', 'inventario.id_producto', '=', 'productos.id')
            ->where('productos.id_empresa', $ctx['empresa_id'])
            ->whereNull('inventario.deleted_at')
            ->selectRaw('SUM(inventario.stock * COALESCE(NULLIF(productos.costo_promedio, 0), productos.costo, 0)) as total')
            ->value('total');

        $metodo = $ctx['empresa']->valor_inventario ?? 'Promedio';

        return [
            'contenido' => [
                'total_balance' => $totalBalance,
                'total_modulo_inventario' => round($valorModulo ?? 0, 2),
                'metodo_valuacion' => $metodo === 'Promedio' ? 'Costo promedio ponderado' : 'PEPS (FIFO)',
            ],
            'estado' => NotasEstadosFinancierosCatalog::ESTADO_COMPLETA,
        ];
    }

    /** @param  array<string, mixed>  $ctx */
    private function nota07(array $ctx): array
    {
        $L = $ctx['balance_lines'];
        $er = $ctx['estado_resultados'];
        $costo = (float) ($L['propiedad_planta_equipo'] ?? 0);
        $depAcum = (float) ($L['depreciacion_acumulada'] ?? 0);
        $neto = $costo + $depAcum;
        $depAnio = (float) ($er['L']['gasto_venta_deprec'] ?? 0)
            + (float) ($er['L']['gasto_admin_deprec'] ?? 0);

        return [
            'contenido' => [
                'costo_historico' => $costo,
                'depreciacion_acumulada' => $depAcum,
                'valor_libros_neto' => $neto,
                'depreciacion_cargada_anio' => $depAnio,
                'matriz_movimientos' => [
                    ['concepto' => 'Saldo al inicio', 'monto' => null],
                    ['concepto' => 'Adiciones', 'monto' => null],
                    ['concepto' => 'Bajas', 'monto' => null],
                    ['concepto' => 'Depreciación del ejercicio', 'monto' => $depAnio],
                    ['concepto' => 'Saldo al cierre (neto)', 'monto' => $neto],
                ],
                'nota' => 'Matriz detallada por activo no disponible; valores derivados del balance NIIF.',
            ],
            'estado' => abs($neto) >= 0.01 || abs($depAnio) >= 0.01
                ? NotasEstadosFinancierosCatalog::ESTADO_COMPLETA
                : NotasEstadosFinancierosCatalog::ESTADO_PARCIAL,
        ];
    }

    /** @param  array<string, mixed>  $ctx */
    private function nota08(array $ctx): array
    {
        $L = $ctx['balance_lines'];
        $neto = (float) ($L['activos_intangibles'] ?? 0);

        return [
            'contenido' => [
                'valor_libros_neto' => $neto,
                'nota' => $neto == 0.0
                    ? 'La entidad no mantiene activos intangibles significativos al cierre.'
                    : 'Valor derivado del balance NIIF; detalle por activo pendiente de módulo dedicado.',
            ],
            'estado' => NotasEstadosFinancierosCatalog::ESTADO_COMPLETA,
        ];
    }

    /** @param  array<string, mixed>  $ctx */
    private function nota09(array $ctx): array
    {
        $L = $ctx['balance_lines'];
        $corto = (float) ($L['prestamos_corto_plazo'] ?? 0);
        $largo = (float) ($L['prestamos_largo_plazo'] ?? 0);

        return [
            'contenido' => [
                'prestamos_corto_plazo' => $corto,
                'prestamos_largo_plazo' => $largo,
                'total_prestamos' => $corto + $largo,
                'detalle' => [],
                'nota' => 'Desglose por acreedor requiere catálogo de préstamos; totales del balance NIIF.',
            ],
            'estado' => ($corto + $largo) > 0
                ? NotasEstadosFinancierosCatalog::ESTADO_PARCIAL
                : NotasEstadosFinancierosCatalog::ESTADO_COMPLETA,
        ];
    }

    /** @param  array<string, mixed>  $ctx */
    private function nota10(array $ctx): array
    {
        $L = $ctx['balance_lines'];
        $c = $ctx['estado_resultados']['cascada'] ?? [];
        $isrBalance = (float) ($L['isr_por_pagar'] ?? 0);
        $isrNetoEr = (float) ($c['isr_neto'] ?? 0);

        return [
            'contenido' => [
                'utilidad_antes_isr' => (float) ($c[EstadoResultadosNiifSvPresenter::LBL_UTIL_ANTES_ISR] ?? 0),
                'isr_estimado' => (float) ($c['isr_estimado'] ?? 0),
                'pago_cuenta' => (float) ($c['pago_cuenta'] ?? 0),
                'isr_neto_pagar' => $isrNetoEr,
                'isr_por_pagar_balance' => $isrBalance,
                'conciliacion' => [
                    ['concepto' => 'ISR neto estimado (ER)', 'monto' => $isrNetoEr],
                    ['concepto' => 'ISR por pagar (Balance)', 'monto' => $isrBalance],
                    ['concepto' => 'Diferencia', 'monto' => round($isrNetoEr - $isrBalance, 2)],
                ],
            ],
            'estado' => NotasEstadosFinancierosCatalog::ESTADO_COMPLETA,
        ];
    }

    /** @param  array<string, mixed>  $ctx */
    private function nota11(array $ctx): array
    {
        $L = $ctx['balance_lines'];
        $fi = $ctx['fecha_inicio'];
        $ff = $ctx['fecha_fin'];

        $row = PlanillaDetalle::query()
            ->join('planillas', 'planilla_detalles.id_planilla', '=', 'planillas.id')
            ->where('planillas.id_empresa', $ctx['empresa_id'])
            ->whereBetween('planillas.fecha_fin', [$fi->toDateString(), $ff->toDateString()])
            ->selectRaw('COALESCE(SUM(planilla_detalles.isss_patronal), 0) as isss_patronal, COALESCE(SUM(planilla_detalles.afp_patronal), 0) as afp_patronal')
            ->first();

        $totalesPlanilla = [
            'isss_patronal' => (float) ($row->isss_patronal ?? 0),
            'afp_patronal' => (float) ($row->afp_patronal ?? 0),
        ];

        return [
            'contenido' => [
                'afp_por_pagar' => (float) ($L['afp_por_pagar'] ?? 0),
                'isss_por_pagar' => (float) ($L['isss_por_pagar'] ?? 0),
                'saldo_provision_indemnizacion' => (float) ($L['provision_indemnizaciones'] ?? 0),
                'gasto_indemnizaciones_er' => (float) ($ctx['estado_resultados']['L']['gasto_admin_indemniza'] ?? 0),
                'totales_planilla_periodo' => $totalesPlanilla,
            ],
            'estado' => NotasEstadosFinancierosCatalog::ESTADO_COMPLETA,
        ];
    }

    /** @param  array<string, mixed>  $ctx */
    private function nota12(array $ctx): array
    {
        $L = $ctx['balance_lines'];
        $ecp = $this->cambiosPatrimonio->build(
            $ctx['empresa_id'],
            $ctx['fecha_inicio'],
            $ctx['fecha_fin'],
            false,
            true
        );

        return [
            'contenido' => [
                'capital_social_cierre' => (float) ($L['capital_social'] ?? 0),
                'reserva_legal_cierre' => (float) ($L['reserva_legal'] ?? 0),
                'utilidades_retenidas' => (float) ($L['utilidades_retenidas'] ?? 0),
                'movimientos_patrimonio' => $ecp['bloques'][0]['filas'] ?? [],
            ],
            'estado' => NotasEstadosFinancierosCatalog::ESTADO_COMPLETA,
        ];
    }

    /** @param  array<string, mixed>  $ctx */
    private function nota13(array $ctx): array
    {
        $manual = (array) ($ctx['contenido_manual'][13] ?? []);

        return [
            'contenido' => [
                'transacciones_partes_relacionadas' => $manual['transacciones'] ?? '',
                'saldos_pendientes' => $manual['saldos_pendientes'] ?? '',
                'nota' => 'Complete las operaciones con accionistas, directores o entidades vinculadas.',
            ],
            'estado' => ! empty(trim($manual['transacciones'] ?? ''))
                ? NotasEstadosFinancierosCatalog::ESTADO_COMPLETA
                : NotasEstadosFinancierosCatalog::ESTADO_PARCIAL,
        ];
    }

    /** @param  array<string, mixed>  $ctx */
    private function nota14(array $ctx): array
    {
        $manual = (array) ($ctx['contenido_manual'][14] ?? []);
        $texto = $manual['texto'] ?? 'Al cierre del periodo, la administración no identifica contingencias materiales '
            . 'distintas a las reconocidas en los estados financieros, salvo las descritas a continuación: [completar].';

        return [
            'contenido' => ['texto' => $texto],
            'estado' => str_contains($texto, '[completar]')
                ? NotasEstadosFinancierosCatalog::ESTADO_PENDIENTE
                : NotasEstadosFinancierosCatalog::ESTADO_COMPLETA,
        ];
    }

    /** @param  array<string, mixed>  $ctx */
    private function nota15(array $ctx): array
    {
        $manual = (array) ($ctx['contenido_manual'][15] ?? []);
        $texto = $manual['texto'] ?? 'Entre la fecha de cierre y la fecha de autorización para emisión, '
            . 'no se han identificado hechos posteriores que requieran ajuste o revelación adicional: [completar].';

        return [
            'contenido' => ['texto' => $texto],
            'estado' => str_contains($texto, '[completar]')
                ? NotasEstadosFinancierosCatalog::ESTADO_PENDIENTE
                : NotasEstadosFinancierosCatalog::ESTADO_COMPLETA,
        ];
    }

    /** @param  array<string, mixed>  $ctx */
    private function nota16(array $ctx): array
    {
        $manual = (array) ($ctx['contenido_manual'][16] ?? []);
        $aplica = (bool) ($manual['aplica'] ?? false);
        $texto = $manual['texto'] ?? 'No aplica: la entidad opera en un solo segmento de negocio y geográfico.';

        return [
            'contenido' => ['aplica' => $aplica, 'texto' => $texto],
            'estado' => $aplica && trim($texto) === ''
                ? NotasEstadosFinancierosCatalog::ESTADO_PENDIENTE
                : NotasEstadosFinancierosCatalog::ESTADO_COMPLETA,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $notas
     * @param  list<int>  $incluidas
     * @return array<string, mixed>
     */
    private function calcularCompletitud(array $notas, array $incluidas): array
    {
        $total = count($incluidas);
        $completas = 0;
        $parciales = 0;
        $pendientes = 0;
        $bloqueantesOk = true;

        foreach ($incluidas as $n) {
            $estado = $notas[$n]['estado'] ?? NotasEstadosFinancierosCatalog::ESTADO_PENDIENTE;
            match ($estado) {
                NotasEstadosFinancierosCatalog::ESTADO_COMPLETA => $completas++,
                NotasEstadosFinancierosCatalog::ESTADO_PARCIAL => $parciales++,
                default => $pendientes++,
            };

            if (NotasEstadosFinancierosCatalog::DEFINICIONES[$n]['bloqueante_emision'] ?? false) {
                if ($estado !== NotasEstadosFinancierosCatalog::ESTADO_COMPLETA) {
                    $bloqueantesOk = false;
                }
            }
        }

        $pct = $total > 0 ? round(($completas / $total) * 100, 1) : 0.0;

        return [
            'total_notas' => $total,
            'completas' => $completas,
            'parciales' => $parciales,
            'pendientes' => $pendientes,
            'porcentaje' => $pct,
            'puede_emitir' => $bloqueantesOk,
            'notas_bloqueantes' => [1, 2, 3],
        ];
    }

    public function guardarBorrador(array $params): NotasEstadosFinancieros
    {
        $payload = $this->generar($params);
        $registro = NotasEstadosFinancieros::create([
            'id_empresa' => $payload['empresa_id'],
            'periodo_actual' => $payload['periodo_actual'],
            'fecha_inicio' => $payload['fecha_inicio'],
            'fecha_fin' => $payload['fecha_fin'],
            'fecha_aprobacion_junta' => $payload['fecha_aprobacion_junta'],
            'incluir_comparativo' => $payload['incluir_comparativo'],
            'periodo_anterior' => $payload['periodo_anterior'],
            'nivel_detalle' => $payload['nivel_detalle'],
            'notas_a_incluir' => $payload['notas_a_incluir'],
            'configuracion' => $params['configuracion'] ?? null,
            'contenido_manual' => $params['contenido_manual'] ?? null,
            'notas_generadas' => $payload['notas'],
            'completitud' => $payload['completitud'],
            'validaciones_cruzadas' => $payload['validaciones_cruzadas'],
            'estado' => 'borrador',
            'id_usuario_creacion' => Auth::id(),
        ]);

        return $registro;
    }
}
