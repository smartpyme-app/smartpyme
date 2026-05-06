<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Contabilidad\Configuracion;
use App\Models\Contabilidad\Partidas\Detalle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de resultados (Estado de rendimiento financiero) NIIF para PYMES — El Salvador (CVPCPA), USD.
 * Usa **movimiento del rango** (debe/haber) por cuenta hoja, sin IVA (cuentas de balance) en la cascada.
 */
class EstadoResultadosNiifSvPresenter
{
    public const LBL_VENTAS_NETAS = 'VENTAS NETAS';

    public const LBL_COGS = 'COSTO DE VENTAS';

    public const LBL_UTILIDAD_BRUTA = 'UTILIDAD BRUTA';

    public const LBL_TOT_GASTOS_OP = 'TOTAL GASTOS DE OPERACIÓN';

    public const LBL_UTILIDAD_OP = 'UTILIDAD DE OPERACIÓN';

    public const LBL_TOT_OTROS = 'TOTAL OTROS INGRESOS (GASTOS) NETO';

    public const LBL_UTIL_ANTES_RES = 'UTILIDAD ANTES DE RESERVA, LEGAL E IMPUESTOS';

    public const LBL_RESERVA = 'Reserva legal (7% Art. 123 C. Comercio — estimado)';

    public const LBL_UTIL_ANTES_ISR = 'UTILIDAD ANTES DE ISR (estimada)';

    public const LBL_BASE_PAGO = 'Ingresos brutos (base pago a cuenta 1,75%)';

    public const LBL_ISR_EST = 'ISR estimado (tasa Art. 131 C. Tributario)';

    public const LBL_PAGO_CTA = 'Pago a cuenta acreditado (1,75% ing. brutos)';

    public const LBL_ISR_NETO = 'ISR neto a pagar (estimado)';

    public const LBL_UTIL_NETA = 'UTILIDAD NETA DEL EJERCICIO (estimada)';

    private array $L = [];

    /**
     * Prefijos numéricos del código contable para COGS / gasto venta / gasto admin (ordenados de más largo a más corto por grupo).
     *
     * @var array{cogs: array<int, string>, gasto_venta: array<int, string>, gasto_admin: array<int, string>}
     */
    private array $prefijosEstadoResultados = [];

    public function build(int $empresaId, Carbon $startDate, Carbon $endDate): array
    {
        $this->L = $this->emptyLineKeys();
        $this->accumulatePeriod($empresaId, $startDate, $endDate);
        $dias = max(1, (int) $startDate->diffInDays($endDate) + 1);
        $vneta = $this->L['ventas_brutas'] - $this->L['devoluciones_ventas'] - $this->L['descuentos_ventas'];
        $this->L['ingresos_gravables_proyectados'] = max(0.0, (float) $vneta) * (365.0 / $dias);

        $waterfall = $this->computeWaterfall($dias);
        $waterfall['periodo_label_inicio'] = $startDate->toDateString();
        $waterfall['periodo_label_fin'] = $endDate->toDateString();
        $waterfall['periodo_titulo'] = $this->formatPeriodoTitulo($startDate, $endDate);
        $waterfall['dias'] = $dias;

        $ingAn = (float) $this->L['ingresos_gravables_proyectados'];
        $waterfall['tasa_isr_sugerida'] = $ingAn <= 150_000.0 ? 0.25 : 0.30;
        $waterfall['tasa_pago_cuenta'] = 0.0175;
        $waterfall['tasa_reserva_legal'] = 0.07;

        return $waterfall;
    }

    /**
     * Añade comparativa con un período anterior (mismas claves) y crecimiento de ventas.
     */
    public static function applyComparative(array $actual, array $anterior): array
    {
        $actual['kpi'] = $actual['kpi'] ?? [];
        $vnA = (float) ($actual['cascada'][self::LBL_VENTAS_NETAS] ?? 0);
        $vnB = (float) ($anterior['cascada'][self::LBL_VENTAS_NETAS] ?? 0);
        if ($vnB > 0.0005) {
            $actual['kpi']['crec_ventas'] = ($vnA - $vnB) / $vnB;
        } else {
            $actual['kpi']['crec_ventas'] = null;
        }
        $actual['comparativa'] = [
            'anterior' => $anterior,
            'periodo_anterior_titulo' => $anterior['periodo_titulo'] ?? '',
        ];

        return $actual;
    }

    /**
     * Mismo rango inmediatamente anterior (mismo número de días).
     */
    public static function periodoAnterior(Carbon $startDate, Carbon $endDate): array
    {
        $endPrev = (clone $startDate)->subDay()->endOfDay();
        $dias = (int) $startDate->diffInDays($endDate) + 1;
        $startPrev = (clone $endPrev)->subDays($dias - 1)->startOfDay();

        return [$startPrev, $endPrev];
    }

    private function formatPeriodoTitulo(Carbon $start, Carbon $end): string
    {
        if ($start->isSameDay($end)) {
            return 'Al ' . $end->translatedFormat('d \\d\\e F \\d\\e Y');
        }

        return 'Del ' . $start->translatedFormat('d M Y') . ' al ' . $end->translatedFormat('d M Y');
    }

    private function emptyLineKeys(): array
    {
        $keys = [
            'ventas_brutas', 'devoluciones_ventas', 'descuentos_ventas',
            'inventario_inicial', 'compras_brutas', 'fletes_compras', 'devoluciones_compras', 'descuentos_compras', 'inventario_final', 'costo_ventas_directo',
            'gasto_venta_sueldos', 'gasto_venta_comisiones', 'gasto_venta_afp', 'gasto_venta_isss', 'gasto_venta_publicidad', 'gasto_venta_fletes', 'gasto_venta_deprec', 'gasto_venta_otros',
            'gasto_admin_sueldos', 'gasto_admin_afp', 'gasto_admin_isss', 'gasto_admin_alquiler', 'gasto_admin_servicios', 'gasto_admin_papeleria', 'gasto_admin_honorarios_audit', 'gasto_admin_legal',
            'gasto_admin_indemniza', 'gasto_admin_vacaciones', 'gasto_admin_deprec', 'gasto_admin_seguros', 'gasto_admin_otros',
            'otros_ing_arrend', 'otros_ing_intereses', 'otros_ing_utilidad_venta_activo',
            'otros_gas_intereses', 'otros_gas_comisiones_banc', 'otros_gas_perdida_activo', 'otros_ingresos', 'otros_gastos',
            'ingresos_gravables_proyectados',
        ];

        return array_fill_keys($keys, 0.0);
    }

    private function accumulatePeriod(int $empresaId, Carbon $startDate, Carbon $endDate): void
    {
        $this->prefijosEstadoResultados = $this->resolvePrefijosEstadoResultados($empresaId);

        $cuentas = Cuenta::withoutGlobalScopes()
            ->where('id_empresa', $empresaId)
            ->orderBy('codigo')
            ->get();

        $partidaDetalles = Detalle::join('partidas', 'partida_detalles.id_partida', '=', 'partidas.id')
            ->where('partidas.id_empresa', $empresaId)
            ->whereIn('partidas.estado', ['Aplicada', 'Cerrada'])
            ->whereBetween('partidas.fecha', [$startDate, $endDate])
            ->select(
                'partida_detalles.id_cuenta',
                DB::raw('SUM(partida_detalles.debe) as total_debe'),
                DB::raw('SUM(partida_detalles.haber) as total_haber')
            )
            ->groupBy('partida_detalles.id_cuenta')
            ->get()
            ->keyBy('id_cuenta');

        $childrenOf = [];
        foreach ($cuentas as $c) {
            if ($c->id_cuenta_padre) {
                $childrenOf[$c->id_cuenta_padre] = true;
            }
        }

        $leafs = $cuentas->filter(function (Cuenta $c) use ($childrenOf) {
            return empty($childrenOf[$c->id]);
        });

        foreach ($leafs as $cuenta) {
            $d = (float) ($partidaDetalles[$cuenta->id]->total_debe ?? 0);
            $h = (float) ($partidaDetalles[$cuenta->id]->total_haber ?? 0);
            $neto = $this->netoMovimiento($cuenta, $d, $h);

            if (abs($neto) < 0.0005) {
                continue;
            }

            if (! $this->esCuentaResultados($cuenta, $neto)) {
                continue;
            }

            $this->distribuirCuenta($cuenta, $neto, $d, $h);
        }
    }

    private function netoMovimiento(Cuenta $cuenta, float $debe, float $haber): float
    {
        if ($cuenta->naturaleza === 'Deudor') {
            return $debe - $haber;
        }

        return $haber - $debe;
    }

    private function esCuentaResultados(Cuenta $cuenta, float $neto): bool
    {
        $r = mb_strtolower(trim((string) $cuenta->rubro), 'UTF-8');
        if ($r === '' || (strpos($r, 'activo') !== false) || (strpos($r, 'pasivo') !== false)) {
            if (strpos($r, 'ingreso') === false && strpos($r, 'gasto') === false && strpos($r, 'costo') === false) {
                if (strpos($r, 'patrimonio') !== false || strpos($r, 'capital') !== false) {
                    return false;
                }
            }
        }
        if (strpos($r, 'ingreso') === false && strpos($r, 'gasto') === false && strpos($r, 'costo') === false) {
            return false;
        }

        if ($this->esExcluirBalanceEnResultados($cuenta, $r)) {
            return false;
        }

        if ($this->esCuentaCierreIngresoGasto($cuenta, $r)) {
            return false;
        }

        if (abs($neto) < 0.0001) {
            return false;
        }

        return true;
    }

    private function esExcluirBalanceEnResultados(Cuenta $cuenta, string $rubroBajo): bool
    {
        $h = $this->normalize($cuenta->nombre . ' ' . $cuenta->codigo . ' ' . $rubroBajo);

        if (preg_match('/\b(iva|i\.?v\.?a)\b/u', $h)
            && preg_match('/(debito|débito|credito|crédito|fiscal|por pagar|a favor|a pagar|d\/fisc|c\/fisc)/u', $h)) {
            return true;
        }
        if (preg_match('/pago a cuenta|pagos a cuenta|anticipo.*isr|anticipo.*renta/u', $h)) {
            return true;
        }
        if (preg_match('/impuesto s\/|impuesto sbre|diferid.*(activo|pasivo).*(ingreso|gasto|costo)/u', $h)) {
            return true;
        }

        return false;
    }

    private function esCuentaCierreIngresoGasto(Cuenta $cuenta, string $rubroBajo): bool
    {
        $h = $this->normalize($cuenta->nombre . ' ' . $cuenta->codigo);
        if (preg_match('/\b(cierra|cierre|sumaria|sumarias|resultado del ejercicio|utilidad acum|perdida acum|pérdida acum|patrimonio)\b/u', $h)) {
            if (preg_match('/(ingreso|gasto|costo)/u', $rubroBajo)) {
                return true;
            }
        }

        return false;
    }

    private function distribuirCuenta(Cuenta $cuenta, float $neto, float $debe, float $haber): void
    {
        $r = mb_strtolower(trim((string) $cuenta->rubro), 'UTF-8');
        $h = $this->normalize($cuenta->nombre . ' ' . $cuenta->codigo . ' ' . $r);
        $toma = $this->montoEfectoPositivo($r, $neto, $h);

        $codigoDigits = preg_replace('/\D+/', '', (string) $cuenta->codigo);
        $clasePrefijo = $this->claseResultadosPorPrefijoCodigo($codigoDigits);
        if ($clasePrefijo === 'cogs') {
            $this->L['costo_ventas_directo'] += $toma;

            return;
        }
        if ($clasePrefijo === 'gasto_venta') {
            $this->distribuirGasto($h, $toma, true, false);

            return;
        }
        if ($clasePrefijo === 'gasto_admin') {
            $this->distribuirGasto($h, $toma, false, true);

            return;
        }

        if (strpos($r, 'ingreso') !== false) {
            $this->distribuirIngreso($h, $toma, $debe, $haber, $r);

            return;
        }
        if (strpos($r, 'costo') !== false) {
            $this->distribuirCosto($h, $toma);

            return;
        }
        if (strpos($r, 'gasto') !== false) {
            $this->distribuirGasto($h, $toma, false, false);

            return;
        }
    }

    /**
     * @return array{cogs: array<int, string>, gasto_venta: array<int, string>, gasto_admin: array<int, string>}
     */
    private function resolvePrefijosEstadoResultados(int $empresaId): array
    {
        $base = [
            'cogs' => $this->normalizePrefijoLista((array) config('contabilidad.estado_resultados_prefijos.cogs', ['4101'])),
            'gasto_venta' => $this->normalizePrefijoLista((array) config('contabilidad.estado_resultados_prefijos.gasto_venta', ['4102'])),
            'gasto_admin' => $this->normalizePrefijoLista((array) config('contabilidad.estado_resultados_prefijos.gasto_admin', ['4103'])),
        ];
        foreach (['cogs', 'gasto_venta', 'gasto_admin'] as $clave) {
            if ($base[$clave] === []) {
                $base[$clave] = $clave === 'cogs'
                    ? ['4101']
                    : ($clave === 'gasto_venta' ? ['4102'] : ['4103']);
                $base[$clave] = $this->normalizePrefijoLista($base[$clave]);
            }
        }

        if (! Schema::hasColumn('contabilidad_configuracion', 'estado_resultados_prefijos')) {
            return $base;
        }

        $row = Configuracion::withoutGlobalScopes()->where('id_empresa', $empresaId)->first();
        $override = $row?->estado_resultados_prefijos;
        if (! is_array($override)) {
            return $base;
        }

        foreach (['cogs', 'gasto_venta', 'gasto_admin'] as $clave) {
            if (! empty($override[$clave]) && is_array($override[$clave])) {
                $lista = $this->normalizePrefijoLista($override[$clave]);
                if ($lista !== []) {
                    $base[$clave] = $lista;
                }
            }
        }

        return $base;
    }

    /**
     * @param  array<int, mixed>  $prefixes
     * @return array<int, string>
     */
    private function normalizePrefijoLista(array $prefixes): array
    {
        $out = [];
        foreach ($prefixes as $p) {
            $d = preg_replace('/\D+/', '', (string) $p);
            if ($d !== '') {
                $out[] = $d;
            }
        }
        $out = array_values(array_unique($out));
        usort($out, static fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));

        return $out;
    }

    private function claseResultadosPorPrefijoCodigo(string $codigoDigits): ?string
    {
        if ($codigoDigits === '') {
            return null;
        }

        foreach (['cogs', 'gasto_venta', 'gasto_admin'] as $clase) {
            foreach ($this->prefijosEstadoResultados[$clase] ?? [] as $prefix) {
                if ($prefix !== '' && str_starts_with($codigoDigits, $prefix)) {
                    return $clase;
                }
            }
        }

        return null;
    }

    private function montoEfectoPositivo(string $rubro, float $neto, string $h): float
    {
        if (strpos($rubro, 'ingreso') !== false) {
            if ($neto < 0) {
                if (preg_match('/devol|rebaja|nota de credito|nc |descuento|bonific|reembols/u', $h)) {
                    return abs($neto);
                }

                return abs($neto);
            }

            if (preg_match('/devol|rebaja|descuento|nota de credito|nc /u', $h)) {
                return abs($neto);
            }

            return $neto;
        }

        return abs($neto);
    }

    private function distribuirIngreso(string $h, float $monto, float $debe, float $haber, string $r): void
    {
        if (preg_match('/(iva|débito fiscal|debito fiscal|crédito fiscal|credito fiscal)/u', $h)) {
            return;
        }

        if (preg_match('/(devol|reembols|nota de credito|nc |rebaj).*(venta|comercial)/u', $h) || (preg_match('/\bdevoluci/u', $h) && preg_match('/venta|cliente|factura/u', $h))) {
            $this->L['devoluciones_ventas'] += $monto;

            return;
        }
        if (preg_match('/descuent.*venta|rebaja.*venta|bonific.*venta/u', $h) && ! preg_match('/compra/u', $h)) {
            $this->L['descuentos_ventas'] += $monto;

            return;
        }

        if (preg_match('/(arriendo|alquiler|arrendam).*(ingreso|rece|locativo)|ingreso.*(arriendo|alquiler|arrendam)/u', $h) && ! preg_match('/oper|venta|merc|serv.*habitu/u', $h)) {
            if (preg_match('/financ|inversion|inversión|largo|no oper/u', $h)) {
                $this->L['otros_ing_arrend'] += $monto;
            } else {
                $this->L['otros_ingresos'] += $monto;
            }

            return;
        }

        if (preg_match('/interes|interés|rendimiento|inversion|inversión.*(titulo|título|bono)|dividendo/u', $h) && ! preg_match('/prestam|préstamo.*(otorg|dado)|gasto|perdida|pérdida/u', $h)) {
            $this->L['otros_ing_intereses'] += $monto;

            return;
        }
        if (preg_match('/utilidad.*venta.*activo|venta de activo|derecho de uso|baja de activo.*ganan/u', $h)) {
            $this->L['otros_ing_utilidad_venta_activo'] += $monto;

            return;
        }
        if (preg_match('/(venta|vta|ingreso oper|factur|servic|explotac|suscrip|comision.*vta)/u', $h) && ! preg_match('/(compra|arriendo|arrend|interes|interés)/u', $h)) {
            if ($monto < 0) {
                $this->L['devoluciones_ventas'] += abs($monto);
            } else {
                $this->L['ventas_brutas'] += $monto;
            }

            return;
        }

        $this->L['otros_ingresos'] += $monto;
    }

    private function distribuirCosto(string $h, float $monto): void
    {
        if (preg_match('/(costo de venta|costo de ventas|costo de lo vend|costo del bien vend)/u', $h) && ! preg_match('/(depreci|flete|public|venta:)/u', $h)) {
            if (preg_match('/invent|material|materia|peps|fifo|nic 2|variacion|variación|ajuste de invent|ajuste de exist/u', $h)) {
                if (preg_match('/final|cierre|termin|existencia cierre/u', $h)) {
                    $this->L['inventario_final'] += $monto;

                    return;
                }
                if (preg_match('/inicial|apert|apertur|existencia apert|comienzo|apertur/u', $h)) {
                    $this->L['inventario_inicial'] += $monto;

                    return;
                }
            }
            $this->L['costo_ventas_directo'] += $monto;

            return;
        }

        if (preg_match('/(inventario|existencia|mercader(ía|ia)|materia prima|produc termin).*(inicial|apert|apertur|inicio|comienzo|ene )/u', $h)
            || preg_match('/(inventario inicial|inicio de invent|existencia inicial|salida inicial|inv\.?\s*inicial)/u', $h)) {
            $this->L['inventario_inicial'] += $monto;

            return;
        }
        if (preg_match('/(inventario|existencia|mercader(ía|ia)).*(final|termin|cierre|fin )/u', $h)
            || preg_match('/(inventario final|existencia final|inv\.?\s*final|existencia a corte)/u', $h)) {
            $this->L['inventario_final'] += $monto;

            return;
        }

        if (preg_match('/(compras? neta|compra neta|compras del periodo|compras de merc|compras m\/|compras )/u', $h) && ! preg_match('/(devol|flete|segur)/u', $h)) {
            if (preg_match('/(devol|reembols|nc proveedor|devoluci.*compr)/u', $h)) {
                $this->L['devoluciones_compras'] += $monto;

                return;
            }
            if (preg_match('/descuento|bonific|rebaja.*(compr|proveedor)/u', $h)) {
                $this->L['descuentos_compras'] += $monto;

                return;
            }
            $this->L['compras_brutas'] += $monto;

            return;
        }
        if (preg_match('/\bcompras?\b/u', $h) && ! preg_match('/(flete|segur|flete|dui)/u', $h) && ! preg_match('/(venta|invent|activo|proveedor a largo plazo|activo|equipo|maquin|vehic)/u', $h)) {
            if (preg_match('/(devol|reembols|nc proveedor|devoluci.*compr|nota de credito|nc p)/u', $h)) {
                $this->L['devoluciones_compras'] += $monto;
            } elseif (preg_match('/descuento|bonific|descuent.*compr/u', $h)) {
                $this->L['descuentos_compras'] += $monto;
            } else {
                $this->L['compras_brutas'] += $monto;
            }

            return;
        }

        if (preg_match('/(flete|fletes|transporte|traslado|seguro|importa).*(compr|import|entrad|proveedor|merc|adquiri)/u', $h)
            || (preg_match('/(flete|fletes|flete|seguro) sobre (compr|adquisi|merc|import)/u', $h))) {
            $this->L['fletes_compras'] += $monto;

            return;
        }
        if (preg_match('/\bdevol.*(compr|merc|proveedor)|nota de credito de compra|nc c\/ proveedor|devoluci.*(merc|compr|provee)/u', $h)) {
            $this->L['devoluciones_compras'] += $monto;

            return;
        }
        if (preg_match('/descuent.*(compr|sobre compra|en compr|provee)|bonific.*(compr|sobre compra)/u', $h)) {
            $this->L['descuentos_compras'] += $monto;

            return;
        }

        // Cualquier otra cuenta de rubro «costo» que no encaje en inventario/compras va a COGS, no a gasto de venta.
        $this->L['costo_ventas_directo'] += $monto;
    }

    private function distribuirGasto(string $h, float $monto, bool $forzarGastoVenta = false, bool $forzarGastoAdmin = false): void
    {
        if ($forzarGastoVenta && $forzarGastoAdmin) {
            $forzarGastoAdmin = false;
        }
        $enVenta = $forzarGastoVenta ? true : ($forzarGastoAdmin ? false : $this->esGastoVenta($h));
        if (preg_match('/(interes|interés|financ|comision|comisión).*(banc|prestam|préstam|banco)|gasto banc|comisiones banc|gastos banc|intereses sobre prest|intereses s\/p/u', $h)) {
            if (preg_match('/interes|interés|sobre prest|financ|prestam|préstam.*(banc|largo|cp)/u', $h)) {
                $this->L['otros_gas_intereses'] += $monto;
            } else {
                $this->L['otros_gas_comisiones_banc'] += $monto;
            }

            return;
        }
        if (preg_match('/perdida|pérdida.*(venta|baja|retir).*(activo|activos)|(venta|baja) de activo.*(perdida|pérdida|en desc)/u', $h)) {
            $this->L['otros_gas_perdida_activo'] += $monto;

            return;
        }
        if (preg_match('/(sueld|nomina|nómina|planilla|salari).*/u', $h)) {
            if (preg_match('/(afp|pension|pensión|previsional|patron|patronal)/u', $h)) {
                if ($enVenta) {
                    $this->L['gasto_venta_afp'] += $monto;
                } else {
                    $this->L['gasto_admin_afp'] += $monto;
                }
            } elseif (preg_match('/(isss|seguro social|isss\))/u', $h)) {
                if ($enVenta) {
                    $this->L['gasto_venta_isss'] += $monto;
                } else {
                    $this->L['gasto_admin_isss'] += $monto;
                }
            } elseif (preg_match('/(venta|vended|comer|vta|fuerza de venta|fuerzaventa|distribu)/u', $h) || $enVenta) {
                $this->L['gasto_venta_sueldos'] += $monto;
            } else {
                $this->L['gasto_admin_sueldos'] += $monto;
            }

            return;
        }
        if (preg_match('/\bcomis(ión|ion|iones)?\b/u', $h) && preg_match('/(venta|vta|vended|vendedor|comercial|sobre vta|sobre venta)/u', $h) && ! preg_match('/(banc|financ|prestam|préstam|interes|interés)/u', $h)) {
            if (preg_match('/(afp|pension|pensión|previsional)/u', $h)) {
                if ($enVenta) {
                    $this->L['gasto_venta_afp'] += $monto;
                } else {
                    $this->L['gasto_admin_afp'] += $monto;
                }
            } elseif (preg_match('/(isss|seguro social)/u', $h)) {
                if ($enVenta) {
                    $this->L['gasto_venta_isss'] += $monto;
                } else {
                    $this->L['gasto_admin_isss'] += $monto;
                }
            } else {
                $this->L['gasto_venta_comisiones'] += $monto;
            }

            return;
        }
        if (preg_match('/(afp|pension|pensión|previsional|patronal|patron(ál|al)?\s*afp)/u', $h)) {
            if ($enVenta) {
                $this->L['gasto_venta_afp'] += $monto;
            } else {
                $this->L['gasto_admin_afp'] += $monto;
            }

            return;
        }
        if (preg_match('/(isss|seguro social|isss( patronal| patrona)?|cotizacion patronal|cotización patronal)/u', $h)) {
            if ($enVenta) {
                $this->L['gasto_venta_isss'] += $monto;
            } else {
                $this->L['gasto_admin_isss'] += $monto;
            }

            return;
        }
        if (preg_match('/(publici|propag|mercadotec|pauta|anunci)/u', $h)) {
            if ($enVenta) {
                $this->L['gasto_venta_publicidad'] += $monto;
            } else {
                $this->L['gasto_admin_otros'] += $monto;
            }

            return;
        }
        if (preg_match('/(flete|fletes|transporte|despach|zona franca|despach).*(venta|vta|cliente|despach)/u', $h)
            || preg_match('/(flete|transporte) (sobre|de) (venta|vta|remision|despach)/u', $h)) {
            if ($enVenta || preg_match('/(venta|vta|cliente|despach|distribu)/u', $h)) {
                $this->L['gasto_venta_fletes'] += $monto;
            } else {
                $this->L['gasto_venta_fletes'] += $monto;
            }

            return;
        }
        if (preg_match('/(depreci|amortiz|devalu).*(venta|vta|comer|distr)|depreci.*(area|área) de venta|gasto.*(venta|vta|comer)/u', $h)) {
            if ($enVenta || preg_match('/(venta|vta|comer|distr|mercad)/u', $h)) {
                $this->L['gasto_venta_deprec'] += $monto;
            } else {
                $this->L['gasto_admin_deprec'] += $monto;
            }

            return;
        }
        if (preg_match('/(depreci|amortiz|nic\s*8|deterioro)/u', $h)) {
            if ($enVenta) {
                $this->L['gasto_venta_deprec'] += $monto;
            } else {
                $this->L['gasto_admin_deprec'] += $monto;
            }

            return;
        }
        if (preg_match('/(alquiler|arriendo) de (ofic|bodeg|almac|local|local admin)/u', $h) || preg_match('/(alquiler|arriendo) (lugar|sede) admin/u', $h)) {
            if (! preg_match('/(venta|vta|comer|flete)/u', $h)) {
                $this->L['gasto_admin_alquiler'] += $monto;
            } else {
                if ($enVenta) {
                    $this->L['gasto_venta_otros'] += $monto;
                } else {
                    $this->L['gasto_admin_alquiler'] += $monto;
                }
            }

            return;
        }
        if (preg_match('/(luz|energ(ía|ia)|agua|telef|telf|celular|cel|internet|wifi|cable|servic.*basic)/u', $h)) {
            if ($enVenta) {
                $this->L['gasto_venta_otros'] += $monto;
            } else {
                $this->L['gasto_admin_servicios'] += $monto;
            }

            return;
        }
        if (preg_match('/(papel|útil|util|suminis|soporter(ía|ia)|tinta|laser|fotocop|papeler(ía|ia))/u', $h)) {
            if ($enVenta) {
                $this->L['gasto_venta_otros'] += $monto;
            } else {
                $this->L['gasto_admin_papeleria'] += $monto;
            }

            return;
        }
        if (preg_match('/(auditor(ía|ia)|revisor|independ|honorar.*audit|big four|kpmg|deloitte|pwc|ey|ernst)/u', $h)) {
            if ($enVenta) {
                $this->L['gasto_venta_otros'] += $monto;
            } else {
                $this->L['gasto_admin_honorarios_audit'] += $monto;
            }

            return;
        }
        if (preg_match('/(abog|legal|jur(í|i)dic|notar|notar(ía|ia)|fianza|registro|mercantil)/u', $h)) {
            if ($enVenta) {
                $this->L['gasto_venta_otros'] += $monto;
            } else {
                $this->L['gasto_admin_legal'] += $monto;
            }

            return;
        }
        if (preg_match('/(indemn|art\.*\s*58|terminacion|terminación|despid|liquid|prestacion|prestación.*labor|finiquito)/u', $h)) {
            if ($enVenta) {
                $this->L['gasto_venta_otros'] += $monto;
            } else {
                $this->L['gasto_admin_indemniza'] += $monto;
            }

            return;
        }
        if (preg_match('/(vacac|aguinald|bienestar|décimo|decimo|bono.?(navidad|año|legal))/u', $h)) {
            if ($enVenta) {
                $this->L['gasto_venta_otros'] += $monto;
            } else {
                $this->L['gasto_admin_vacaciones'] += $monto;
            }

            return;
        }
        if (preg_match('/(seguro|fianz|fianz\.|caucion|caución|poliz|póliz)/u', $h) && ! preg_match('/(isss|afp|empleado|nomina|nómina|social)/u', $h)) {
            if ($enVenta) {
                $this->L['gasto_venta_otros'] += $monto;
            } else {
                $this->L['gasto_admin_seguros'] += $monto;
            }

            return;
        }
        if ($enVenta) {
            $this->L['gasto_venta_otros'] += $monto;
        } else {
            $this->L['gasto_admin_otros'] += $monto;
        }
    }

    private function esGastoVenta(string $h): bool
    {
        if (preg_match('/(venta|vta|vended|comer|distr|mercad|fuerzav|fuerzadeventa|fuerzade venta|publicid|flete|logistic de venta|canal|punto de venta|pdv|mostrador)/u', $h)) {
            if (! preg_match('/(nómina|nomina) general|sueldo.*admin|admin(istr|istrativ)|ofic|contabil(idad)? de admin/u', $h)) {
                return true;
            }
        }
        if (preg_match('/(depart|depto|dpto|área|area) de venta|gasto( de)? (venta|vta|vended|comer)/u', $h)) {
            return true;
        }

        return false;
    }

    private function computeWaterfall(int $dias): array
    {
        $L = $this->L;
        $ventasNetas = $L['ventas_brutas'] - $L['devoluciones_ventas'] - $L['descuentos_ventas'];
        $comprasNetaCogs = $L['compras_brutas'] + $L['fletes_compras'] - $L['devoluciones_compras'] - $L['descuentos_compras'];
        $cogsDetallado = $L['inventario_inicial'] + $comprasNetaCogs - $L['inventario_final'];

        if (abs($cogsDetallado) > 0.0005) {
            $cogs = $cogsDetallado;
        } else {
            $cogs = $L['costo_ventas_directo'];
        }

        $utilidadBruta = $ventasNetas - $cogs;
        $sumGv = 0.0;
        $keysGv = [
            'gasto_venta_sueldos', 'gasto_venta_comisiones', 'gasto_venta_afp', 'gasto_venta_isss', 'gasto_venta_publicidad',
            'gasto_venta_fletes', 'gasto_venta_deprec', 'gasto_venta_otros',
        ];
        foreach ($keysGv as $k) {
            $sumGv += $L[$k];
        }
        $sumGa = 0.0;
        $keysGa = [
            'gasto_admin_sueldos', 'gasto_admin_afp', 'gasto_admin_isss', 'gasto_admin_alquiler', 'gasto_admin_servicios', 'gasto_admin_papeleria',
            'gasto_admin_honorarios_audit', 'gasto_admin_legal', 'gasto_admin_indemniza', 'gasto_admin_vacaciones', 'gasto_admin_deprec', 'gasto_admin_seguros', 'gasto_admin_otros',
        ];
        foreach ($keysGa as $k) {
            $sumGa += $L[$k];
        }
        $totOp = $sumGv + $sumGa;
        $utilOp = $utilidadBruta - $totOp;
        $otrosIng = $L['otros_ing_arrend'] + $L['otros_ing_intereses'] + $L['otros_ing_utilidad_venta_activo'] + $L['otros_ingresos'];
        $otrosGas = $L['otros_gas_intereses'] + $L['otros_gas_comisiones_banc'] + $L['otros_gas_perdida_activo'] + $L['otros_gastos'];
        $otrosN = $otrosIng - $otrosGas;
        $uAntesR = $utilOp + $otrosN;

        $ingAn = (float) $L['ingresos_gravables_proyectados'];
        $tasaIsr = $ingAn <= 150_000.0 ? 0.25 : 0.30;
        $ingBrutosBase = (float) $L['ventas_brutas'];

        if ($uAntesR > 0.0005) {
            $reservaMonto = $uAntesR * 0.07;
            $uDespReserva = $uAntesR - $reservaMonto;
        } else {
            $reservaMonto = 0.0;
            $uDespReserva = $uAntesR;
        }

        if ($uDespReserva > 0.0005) {
            $isrEst = $uDespReserva * $tasaIsr;
        } else {
            $isrEst = 0.0;
        }

        $pagoCuenta = $ingBrutosBase * 0.0175;
        $isrNeto = max(0.0, $isrEst - $pagoCuenta);
        if ($uDespReserva <= 0) {
            $utilNeta = $uDespReserva;
        } else {
            $utilNeta = $uDespReserva - $isrNeto;
        }

        $gastoVentaEtq = $this->etiquetasGastosVenta();
        $gastoAdminEtq = $this->etiquetasGastosAdmin();
        $gastosVentaLineas = [];
        foreach ($keysGv as $k) {
            if (abs($L[$k]) > 0.0005) {
                $gastosVentaLineas[] = ['k' => $k, 'etiqueta' => $gastoVentaEtq[$k] ?? $k, 'monto' => $L[$k]];
            }
        }
        $gastosAdminLineas = [];
        foreach ($keysGa as $k) {
            if (abs($L[$k]) > 0.0005) {
                $gastosAdminLineas[] = ['k' => $k, 'etiqueta' => $gastoAdminEtq[$k] ?? $k, 'monto' => $L[$k]];
            }
        }

        $kpi = $this->kpi(
            $ventasNetas,
            $utilidadBruta,
            $utilOp,
            $uDespReserva,
            $utilNeta,
            $cogs,
            $isrEst
        );

        return [
            'L' => $L,
            'cascada' => [
                'ventas_brutas' => $L['ventas_brutas'],
                'devoluciones_ventas' => $L['devoluciones_ventas'],
                'descuentos_ventas' => $L['descuentos_ventas'],
                self::LBL_VENTAS_NETAS => $ventasNetas,
                'inventario_inicial' => $L['inventario_inicial'],
                'compras_brutas' => $L['compras_brutas'],
                'fletes_compras' => $L['fletes_compras'],
                'devoluciones_compras' => $L['devoluciones_compras'],
                'descuentos_compras' => $L['descuentos_compras'],
                'inventario_final' => $L['inventario_final'],
                self::LBL_COGS => $cogs,
                self::LBL_UTILIDAD_BRUTA => $utilidadBruta,
                'gastos_venta_detalle' => array_combine($keysGv, array_map(fn ($k) => $L[$k], $keysGv)),
                'gastos_venta_lineas' => $gastosVentaLineas,
                'total_gastos_venta' => $sumGv,
                'gastos_admin_detalle' => array_combine($keysGa, array_map(fn ($k) => $L[$k], $keysGa)),
                'gastos_admin_lineas' => $gastosAdminLineas,
                'total_gastos_admin' => $sumGa,
                self::LBL_TOT_GASTOS_OP => $totOp,
                self::LBL_UTILIDAD_OP => $utilOp,
                'otros_ing' => $otrosIng,
                'otros_gas' => $otrosGas,
                'otros_ingresos' => $L['otros_ingresos'],
                'otros_gastos' => $L['otros_gastos'],
                self::LBL_TOT_OTROS => $otrosN,
                self::LBL_UTIL_ANTES_RES => $uAntesR,
                'reserva_legal' => $reservaMonto,
                self::LBL_UTIL_ANTES_ISR => $uDespReserva,
                'base_ingresos_brutos' => $ingBrutosBase,
                'isr_tasa' => $tasaIsr,
                'tasa_pago_cuenta' => 0.0175,
                'isr_estimado' => $isrEst,
                'pago_cuenta' => $pagoCuenta,
                'isr_neto' => $isrNeto,
                self::LBL_UTIL_NETA => $utilNeta,
            ],
            'kpi' => $kpi,
            'dias' => $dias,
        ];
    }

    private function etiquetasGastosVenta(): array
    {
        return [
            'gasto_venta_sueldos' => 'Sueldos y salarios — área de ventas',
            'gasto_venta_comisiones' => 'Comisiones sobre ventas',
            'gasto_venta_afp' => 'AFP patronal ventas (estimada)',
            'gasto_venta_isss' => 'ISSS patronal ventas (estimada)',
            'gasto_venta_publicidad' => 'Publicidad y propaganda',
            'gasto_venta_fletes' => 'Fletes y transporte sobre ventas',
            'gasto_venta_deprec' => 'Depreciación — activos de ventas',
            'gasto_venta_otros' => 'Otros gastos de venta',
        ];
    }

    private function etiquetasGastosAdmin(): array
    {
        return [
            'gasto_admin_sueldos' => 'Sueldos y salarios — administración',
            'gasto_admin_afp' => 'AFP patronal — administración',
            'gasto_admin_isss' => 'ISSS patronal — administración',
            'gasto_admin_alquiler' => 'Alquiler de oficinas',
            'gasto_admin_servicios' => 'Servicios básicos (agua, luz, telefonía, internet)',
            'gasto_admin_papeleria' => 'Papelería, útiles y suministros',
            'gasto_admin_honorarios_audit' => 'Honorarios de auditoría externa',
            'gasto_admin_legal' => 'Honorarios legales y notariales',
            'gasto_admin_indemniza' => 'Provisión indemnizaciones (Art. 58 C. Trabajo SV)',
            'gasto_admin_vacaciones' => 'Provisión vacaciones y aguinaldo',
            'gasto_admin_deprec' => 'Depreciación — activos administrativos',
            'gasto_admin_seguros' => 'Seguros y fianzas',
            'gasto_admin_otros' => 'Otros gastos administrativos',
        ];
    }

    private function kpi(
        float $ventasNetas,
        float $uBruta,
        float $uOp,
        float $uAnteIsr,
        float $uNeta,
        float $cogs,
        float $isrEst
    ): array {
        $p = $ventasNetas > 0.0005
            ? function (float $x) use ($ventasNetas) { return $x / $ventasNetas; }
            : fn () => null;
        $cargaFiscal = ($uAnteIsr > 0.0005 && $isrEst > 0) ? $isrEst / $uAnteIsr : null;
        $costoPct = $ventasNetas > 0.0005 ? $cogs / $ventasNetas : null;

        return [
            'margen_bruto' => $p($uBruta),
            'margen_operativo' => $p($uOp),
            'margen_neto' => $p($uNeta),
            'crec_ventas' => null,
            'carga_fiscal_isr' => $cargaFiscal,
            'costo_ventas_pct' => $costoPct,
        ];
    }

    private function normalize(string $text): string
    {
        $t = mb_strtolower(trim($text), 'UTF-8');

        return strtr($t, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ñ' => 'n', 'ü' => 'u',
        ]);
    }
}
