<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Contabilidad\Partidas\Detalle;
use Carbon\Carbon;

/**
 * Estado de cambios en el patrimonio neto — NIIF para PYMES (Sección 6), El Salvador (CVPCPA), USD.
 */
class CambiosPatrimonioNiifSvPresenter
{
    /** @var list<string> */
    public const COLUMN_KEYS = [
        'capital_social',
        'reserva_legal',
        'utilidades_retenidas',
        'utilidad_ejercicio',
        'superavit_revaluacion',
        'otras_reservas',
    ];

    /** @var array<string, string> */
    public const COLUMN_LABELS = [
        'capital_social' => 'Capital social',
        'reserva_legal' => 'Reserva legal',
        'utilidades_retenidas' => 'Utilidades retenidas',
        'utilidad_ejercicio' => 'Utilidad (pérdida) del ejercicio',
        'superavit_revaluacion' => 'Superávit por revaluación',
        'otras_reservas' => 'Otras reservas',
    ];

    public function __construct(
        private BalanceGeneralNiifSvPresenter $balance,
        private EstadoResultadosNiifSvPresenter $estadoResultados,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(
        int $empresaId,
        Carbon $fechaInicio,
        Carbon $fechaFin,
        bool $incluirDosAnios = false,
        bool $soloMovimientos = false,
    ): array {
        $fi = $fechaInicio->copy()->startOfDay();
        $ff = $fechaFin->copy()->endOfDay();

        $periodos = $this->resolvePeriodos($fi, $ff, $incluirDosAnios);
        $bloques = [];
        foreach ($periodos as $periodo) {
            $bloques[] = $this->buildPeriodo($empresaId, $periodo['inicio'], $periodo['fin'], $soloMovimientos);
        }

        $columnas = $this->resolveColumnasVisibles($bloques);
        $validaciones = $this->validarReporte($bloques);

        return [
            'moneda' => 'USD',
            'periodo_titulo' => $this->formatPeriodoTitulo($fi, $ff),
            'incluir_dos_anios' => $incluirDosAnios,
            'mostrar_solo_movimientos' => $soloMovimientos,
            'columnas' => $columnas,
            'bloques' => $bloques,
            'validaciones' => $validaciones,
        ];
    }

    public static function calcularReservaLegal(float $utilidadNeta, float $reservaAcumulada, float $capitalSocial): float
    {
        if ($utilidadNeta <= 0.0005) {
            return 0.0;
        }

        $tasaReserva = 0.07;
        $topeReserva = $capitalSocial * 0.20;

        if ($reservaAcumulada >= $topeReserva - 0.0005) {
            return 0.0;
        }

        $reservaPropuesta = $utilidadNeta * $tasaReserva;
        $espacioDisponible = $topeReserva - $reservaAcumulada;

        return max(0.0, min($reservaPropuesta, $espacioDisponible));
    }

    /**
     * @return list<array{inicio: Carbon, fin: Carbon, anio: int}>
     */
    private function resolvePeriodos(Carbon $fi, Carbon $ff, bool $incluirDosAnios): array
    {
        $anioFin = (int) $ff->year;
        $anioIni = (int) $fi->year;

        if ($incluirDosAnios && $anioFin > $anioIni) {
            $anios = range($anioIni, $anioFin);
        } else {
            $anios = [$anioFin];
        }

        $periodos = [];
        foreach ($anios as $anio) {
            $inicio = Carbon::create($anio, 1, 1)->startOfDay();
            $fin = Carbon::create($anio, 12, 31)->endOfDay();
            if ($inicio->lt($fi)) {
                $inicio = $fi->copy();
            }
            if ($fin->gt($ff)) {
                $fin = $ff->copy()->endOfDay();
            }
            if ($inicio->lte($fin)) {
                $periodos[] = ['inicio' => $inicio, 'fin' => $fin, 'anio' => $anio];
            }
        }

        return $periodos;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPeriodo(int $empresaId, Carbon $fi, Carbon $ff, bool $soloMovimientos): array
    {
        [$openStart, $openEnd] = FlujoEfectivoHibridoNiifSvPresenter::openingSnapshotRange($fi);
        $snapOpen = $this->balance->rawLines($empresaId, $openStart, $openEnd);
        $snapClose = $this->balance->rawLines($empresaId, $fi, $ff);

        $opening = $this->extractPatrimonio($snapOpen['lines']);
        $closing = $this->extractPatrimonio($snapClose['lines']);

        $er = $this->estadoResultados->build($empresaId, $fi, $ff);
        $utilidadNeta = (float) ($er['cascada'][EstadoResultadosNiifSvPresenter::LBL_UTIL_NETA] ?? 0);
        $reservaEstimadaEr = (float) ($er['cascada']['reserva_legal'] ?? 0);

        $reservaLegal = self::calcularReservaLegal(
            $utilidadNeta,
            (float) $opening['reserva_legal'],
            (float) $opening['capital_social'],
        );
        if ($reservaLegal < 0.0005 && $reservaEstimadaEr > 0.0005 && $utilidadNeta > 0) {
            $reservaLegal = $reservaEstimadaEr;
        }

        $ledger = $this->aggregateLedgerMovements($empresaId, $fi, $ff);

        $filas = [];
        $filas[] = $this->filaSaldo(
            'saldo_apertura',
            'Saldo al ' . $fi->translatedFormat('j \\d\\e F \\d\\e Y'),
            $opening,
            true,
        );

        if (abs($ledger['ajustes_politicas']) > 0.01) {
            $filas[] = $this->filaMovimiento(
                'ajustes_politicas',
                'Ajustes por cambios en políticas contables (retroactivos)',
                $this->valoresEnColumna('otras_reservas', $ledger['ajustes_politicas']),
            );
        }

        if (abs($ledger['correcciones']) > 0.01) {
            $vals = $ledger['correcciones_por_columna'];
            $filas[] = $this->filaMovimiento(
                'correcciones',
                'Correcciones de errores de períodos anteriores (NIC 8)',
                $vals,
            );
        }

        if (abs($utilidadNeta) > 0.0005) {
            $colUtil = abs((float) $closing['utilidad_ejercicio']) > 0.0005 ? 'utilidad_ejercicio' : 'utilidades_retenidas';
            $filas[] = $this->filaMovimiento(
                'utilidad_neta',
                'Utilidad neta del ejercicio ' . $fi->year . ($utilidadNeta < 0 ? ' (pérdida)' : ''),
                $this->valoresEnColumna($colUtil, $utilidadNeta),
                $utilidadNeta < 0,
            );
        }

        if ($reservaLegal > 0.0005) {
            $vals = $this->emptyValores();
            $vals['reserva_legal'] = $reservaLegal;
            $vals['utilidades_retenidas'] = -$reservaLegal;
            $filas[] = $this->filaMovimiento(
                'reserva_legal',
                'Constitución de reserva legal (7% Art. 123 C. Comercio)',
                $vals,
            );
        }

        if (abs($ledger['dividendos']) > 0.0005) {
            $filas[] = $this->filaMovimiento(
                'dividendos',
                'Dividendos decretados y pagados',
                $this->valoresEnColumna('utilidades_retenidas', -abs($ledger['dividendos'])),
            );
        }

        if (abs($ledger['capitalizacion']) > 0.0005) {
            $vals = $this->emptyValores();
            $vals['capital_social'] = abs($ledger['capitalizacion']);
            $vals['utilidades_retenidas'] = -abs($ledger['capitalizacion']);
            $filas[] = $this->filaMovimiento(
                'capitalizacion',
                'Capitalización de utilidades',
                $vals,
            );
        }

        if (abs($ledger['aportaciones']) > 0.0005) {
            $filas[] = $this->filaMovimiento(
                'aportaciones',
                'Aportaciones adicionales de socios',
                $this->valoresEnColumna('capital_social', $ledger['aportaciones']),
            );
        }

        if (abs($ledger['revaluacion']) > 0.0005) {
            $filas[] = $this->filaMovimiento(
                'revaluacion',
                'Revaluación de activos (NIIF Sección 17)',
                $this->valoresEnColumna('superavit_revaluacion', $ledger['revaluacion']),
            );
        }

        if (abs($ledger['otras_reservas_mov']) > 0.0005) {
            $filas[] = $this->filaMovimiento(
                'otras_reservas',
                'Movimientos en otras reservas',
                $this->valoresEnColumna('otras_reservas', $ledger['otras_reservas_mov']),
            );
        }

        $residual = $this->calcularResidual($opening, $closing, $filas);
        if ($this->tieneMovimiento($residual)) {
            $filas[] = $this->filaMovimiento(
                'otros_cambios',
                'Otros cambios reconocidos directamente en patrimonio',
                $residual,
            );
        }

        $filas[] = $this->filaSaldo(
            'saldo_cierre',
            'Saldo al ' . $ff->translatedFormat('j \\d\\e F \\d\\e Y'),
            $closing,
            true,
        );

        if ($soloMovimientos) {
            $filas = array_values(array_filter(
                $filas,
                fn (array $f): bool => empty($f['es_saldo']) && $this->tieneMovimiento($f['valores'] ?? []),
            ));
        }

        $totalPatrimonioBalance = $this->totalPatrimonio($closing);
        $totalPatrimonioEstado = (float) ($filas[count($filas) - 1]['total'] ?? $totalPatrimonioBalance);

        return [
            'anio' => (int) $fi->year,
            'fecha_inicio' => $fi->toDateString(),
            'fecha_fin' => $ff->toDateString(),
            'titulo' => 'Ejercicio ' . $fi->year,
            'apertura' => $opening,
            'cierre' => $closing,
            'utilidad_neta' => $utilidadNeta,
            'reserva_legal_calculada' => $reservaLegal,
            'filas' => $filas,
            'total_patrimonio_balance' => $totalPatrimonioBalance,
            'total_patrimonio_estado' => $totalPatrimonioEstado,
            'cuadre_balance' => abs($totalPatrimonioEstado - $totalPatrimonioBalance) < 0.02,
            'diferencia_cuadre' => $totalPatrimonioEstado - $totalPatrimonioBalance,
            'validacion_reserva_tope' => self::validarTopeReservaLegal(
                (float) $closing['reserva_legal'],
                (float) $closing['capital_social'],
            ),
            'validacion_dividendos' => self::validarDividendos(
                (float) $opening['utilidades_retenidas'] + max(0.0, $utilidadNeta) - $reservaLegal,
                abs($ledger['dividendos']),
            ),
        ];
    }

    /**
     * @param  array<string, float>  $lines
     * @return array<string, float>
     */
    private function extractPatrimonio(array $lines): array
    {
        $out = $this->emptyValores();
        foreach (self::COLUMN_KEYS as $key) {
            $out[$key] = (float) ($lines[$key] ?? 0.0);
        }

        return $out;
    }

    /**
     * @return array<string, float>
     */
    private function emptyValores(): array
    {
        return array_fill_keys(self::COLUMN_KEYS, 0.0);
    }

    /**
     * @return array<string, float>
     */
    private function valoresEnColumna(string $columna, float $monto): array
    {
        $vals = $this->emptyValores();
        $vals[$columna] = $monto;

        return $vals;
    }

    /**
     * @param  array<string, float>  $valores
     */
    private function filaSaldo(string $clave, string $etiqueta, array $valores, bool $negrita): array
    {
        return [
            'clave' => $clave,
            'etiqueta' => $etiqueta,
            'valores' => $valores,
            'total' => $this->totalPatrimonio($valores),
            'es_saldo' => true,
            'es_negativo' => false,
            'negrita' => $negrita,
        ];
    }

    /**
     * @param  array<string, float>  $valores
     */
    private function filaMovimiento(string $clave, string $etiqueta, array $valores, bool $esNegativo = false): array
    {
        return [
            'clave' => $clave,
            'etiqueta' => $etiqueta,
            'valores' => $valores,
            'total' => array_sum($valores),
            'es_saldo' => false,
            'es_negativo' => $esNegativo,
            'negrita' => false,
        ];
    }

    /**
     * @param  array<string, float>  $valores
     */
    private function totalPatrimonio(array $valores): float
    {
        return array_sum($valores);
    }

    /**
     * @param  array<string, float>  $valores
     */
    private function tieneMovimiento(array $valores): bool
    {
        foreach ($valores as $v) {
            if (abs((float) $v) > 0.0005) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, float>
     */
    private function calcularResidual(array $opening, array $closing, array $filas): array
    {
        $residual = $this->emptyValores();
        foreach (self::COLUMN_KEYS as $col) {
            $delta = (float) $closing[$col] - (float) $opening[$col];
            $explicado = 0.0;
            foreach ($filas as $fila) {
                if (! empty($fila['es_saldo'])) {
                    continue;
                }
                $explicado += (float) ($fila['valores'][$col] ?? 0);
            }
            $residual[$col] = $delta - $explicado;
        }

        return $residual;
    }

    /**
     * @return array<string, mixed>
     */
    private function aggregateLedgerMovements(int $empresaId, Carbon $fi, Carbon $ff): array
    {
        $cuentas = Cuenta::withoutGlobalScope('empresa')
            ->where('id_empresa', $empresaId)
            ->get()
            ->keyBy('id');

        $movs = Detalle::query()
            ->join('partidas', 'partida_detalles.id_partida', '=', 'partidas.id')
            ->where('partidas.id_empresa', $empresaId)
            ->whereIn('partidas.estado', ['Aplicada', 'Cerrada'])
            ->whereBetween('partidas.fecha', [$fi->toDateString(), $ff->toDateString()])
            ->select(
                'partida_detalles.id_cuenta',
                'partida_detalles.debe',
                'partida_detalles.haber',
                'partidas.concepto as partida_concepto',
                'partidas.tipo as partida_tipo',
            )
            ->get();

        $out = [
            'dividendos' => 0.0,
            'capitalizacion' => 0.0,
            'aportaciones' => 0.0,
            'revaluacion' => 0.0,
            'otras_reservas_mov' => 0.0,
            'ajustes_politicas' => 0.0,
            'correcciones' => 0.0,
            'correcciones_por_columna' => $this->emptyValores(),
        ];

        foreach ($movs as $mov) {
            $cuenta = $cuentas->get($mov->id_cuenta);
            if (! $cuenta) {
                continue;
            }

            $col = $this->classifyEquityColumn($cuenta);
            if ($col === null) {
                continue;
            }

            $neto = $this->netoPatrimonio($cuenta, (float) $mov->debe, (float) $mov->haber);
            if (abs($neto) < 0.0005) {
                continue;
            }

            $concepto = $this->normalize($mov->partida_concepto . ' ' . ($mov->partida_tipo ?? ''));

            if ($this->isCorreccion($concepto)) {
                $out['correcciones'] += abs($neto);
                $out['correcciones_por_columna'][$col] += $neto;

                continue;
            }

            if ($this->isAjustePolitica($concepto)) {
                $out['ajustes_politicas'] += $neto;

                continue;
            }

            if ($col === 'utilidades_retenidas' && $this->isDividendo($concepto)) {
                $out['dividendos'] += abs($neto);

                continue;
            }

            if ($col === 'capital_social' && $this->isCapitalizacion($concepto)) {
                $out['capitalizacion'] += abs($neto);

                continue;
            }

            if ($col === 'capital_social' && $this->isAportacion($concepto)) {
                $out['aportaciones'] += $neto;

                continue;
            }

            if ($col === 'superavit_revaluacion') {
                $out['revaluacion'] += $neto;

                continue;
            }

            if ($col === 'otras_reservas') {
                $out['otras_reservas_mov'] += $neto;
            }
        }

        return $out;
    }

    private function classifyEquityColumn(Cuenta $cuenta): ?string
    {
        $rubro = $this->normalize($cuenta->rubro ?? '');
        $haystack = $this->normalize($cuenta->codigo . ' ' . $cuenta->nombre . ' ' . ($cuenta->descripcion ?? ''));

        if (
            strpos($rubro, 'capital') === false
            && strpos($rubro, 'patrimonio') === false
            && strpos($rubro, 'resultado') === false
        ) {
            return null;
        }

        if (preg_match('/utilidad.*reten|resultado.*acum|utilidades retenidas/u', $haystack)) {
            return 'utilidades_retenidas';
        }
        if (preg_match('/reserva legal/u', $haystack)) {
            return 'reserva_legal';
        }
        if (preg_match('/superavit|superávit|revalu/u', $haystack)) {
            return 'superavit_revaluacion';
        }
        if (preg_match('/utilidad.*ejerc|resultado.*ejerc|perdida.*ejerc|pérdida.*ejerc/u', $haystack)) {
            return 'utilidad_ejercicio';
        }
        if (preg_match('/capital social|capital suscrit|aporte.*socio/u', $haystack)) {
            return 'capital_social';
        }
        if (preg_match('/reserva/u', $haystack) && ! preg_match('/legal/u', $haystack)) {
            return 'otras_reservas';
        }

        return 'utilidades_retenidas';
    }

    private function netoPatrimonio(Cuenta $cuenta, float $debe, float $haber): float
    {
        if ($cuenta->naturaleza === 'Deudor') {
            return $debe - $haber;
        }

        return $haber - $debe;
    }

    private function isDividendo(string $h): bool
    {
        return (bool) preg_match('/dividendo|distribuc.*util|reparto.*util|utilidad.*distrib/u', $h);
    }

    private function isCapitalizacion(string $h): bool
    {
        return (bool) preg_match('/capitaliz|capitalizaci|utilidad.*capital/u', $h);
    }

    private function isAportacion(string $h): bool
    {
        return (bool) preg_match('/aport|aumento.*capital|suscrip/u', $h);
    }

    private function isCorreccion(string $h): bool
    {
        return (bool) preg_match('/correccion|corrección|error.*anterior|nic\s*8|reexpres/u', $h);
    }

    private function isAjustePolitica(string $h): bool
    {
        return (bool) preg_match('/politica contable|política contable|cambio.*politica|retroactiv/u', $h);
    }

    private function normalize(string $text): string
    {
        $t = mb_strtolower(trim($text), 'UTF-8');

        return strtr($t, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ñ' => 'n', 'ü' => 'u',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $bloques
     * @return list<array{clave: string, etiqueta: string}>
     */
    private function resolveColumnasVisibles(array $bloques): array
    {
        $visible = [];
        foreach (self::COLUMN_KEYS as $key) {
            $show = false;
            foreach ($bloques as $bloque) {
                foreach (['apertura', 'cierre'] as $snap) {
                    if (abs((float) ($bloque[$snap][$key] ?? 0)) > 0.0005) {
                        $show = true;
                        break 2;
                    }
                }
                foreach ($bloque['filas'] ?? [] as $fila) {
                    if (abs((float) ($fila['valores'][$key] ?? 0)) > 0.0005) {
                        $show = true;
                        break 2;
                    }
                }
            }
            if ($show || $key === 'capital_social' || $key === 'utilidades_retenidas') {
                $visible[] = ['clave' => $key, 'etiqueta' => self::COLUMN_LABELS[$key]];
            }
        }

        return $visible;
    }

    /**
     * @param  list<array<string, mixed>>  $bloques
     * @return array<string, mixed>
     */
    private function validarReporte(array $bloques): array
    {
        $alertas = [];
        $cuadra = true;

        foreach ($bloques as $bloque) {
            if (empty($bloque['cuadre_balance'])) {
                $cuadra = false;
                $alertas[] = sprintf(
                    'El patrimonio del estado no coincide con el balance al %s. Diferencia: $%s',
                    $bloque['fecha_fin'] ?? '',
                    number_format(abs((float) ($bloque['diferencia_cuadre'] ?? 0)), 2),
                );
            }
            if (empty($bloque['validacion_reserva_tope']['ok'])) {
                $alertas[] = (string) ($bloque['validacion_reserva_tope']['mensaje'] ?? 'Reserva legal excede tope.');
            }
            if (empty($bloque['validacion_dividendos']['ok'])) {
                $alertas[] = (string) ($bloque['validacion_dividendos']['mensaje'] ?? 'Dividendos exceden utilidades.');
            }
        }

        return [
            'cuadra_con_balance' => $cuadra,
            'alertas' => $alertas,
        ];
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public static function validarTopeReservaLegal(float $reservaAcumulada, float $capitalSocial): array
    {
        $tope = $capitalSocial * 0.20;
        if ($reservaAcumulada > $tope + 0.01) {
            return [
                'ok' => false,
                'mensaje' => sprintf(
                    'Reserva legal ($%s) excede el tope legal ($%s)',
                    number_format($reservaAcumulada, 2),
                    number_format($tope, 2),
                ),
            ];
        }

        return ['ok' => true, 'mensaje' => ''];
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public static function validarDividendos(float $utilidadesRetenidas, float $dividendosPropuestos): array
    {
        if ($dividendosPropuestos > 0.0005 && $utilidadesRetenidas < $dividendosPropuestos - 0.01) {
            return [
                'ok' => false,
                'mensaje' => 'No hay utilidades suficientes para decretar los dividendos registrados.',
            ];
        }

        return ['ok' => true, 'mensaje' => ''];
    }

    private function formatPeriodoTitulo(Carbon $start, Carbon $end): string
    {
        if ($start->isSameDay($end)) {
            return 'Al ' . $end->translatedFormat('j \\d\\e F \\d\\e Y');
        }

        return 'Del ' . $start->translatedFormat('j \\d\\e F \\d\\e Y') . ' al ' . $end->translatedFormat('j \\d\\e F \\d\\e Y');
    }
}
