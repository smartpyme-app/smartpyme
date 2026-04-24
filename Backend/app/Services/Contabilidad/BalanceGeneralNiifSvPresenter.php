<?php

namespace App\Services\Contabilidad;

use App\Models\Contabilidad\Catalogo\Cuenta;
use App\Models\Contabilidad\Partidas\Detalle;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Presentación de Balance General (Estado de Situación Financiera) según estructura NIIF para PYMES,
 * orientado a El Salvador (CVPCPA), en USD.
 *
 * Clasifica cuentas de detalle (hojas del catálogo) en líneas estándar; saldos no clasificados
 * van a partidas genéricas "Otros …" del bloque correspondiente.
 */
class BalanceGeneralNiifSvPresenter
{
    /** @var array<string, float> */
    private array $lines = [];

    public function build(int $empresaId, Carbon $startDate, Carbon $endDate): array
    {
        $this->lines = $this->emptyLineKeys();

        $cuentasJerarquicas = Cuenta::withoutGlobalScopes()
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

        $saldosIniciales = $this->obtenerSaldosIniciales($startDate, $empresaId);

        $idACodigo = [];
        foreach ($cuentasJerarquicas as $cuenta) {
            $idACodigo[$cuenta->id] = $cuenta->codigo;
        }

        $cuentasSaldos = [];
        foreach ($cuentasJerarquicas as $cuenta) {
            $id = $cuenta->id;
            $codigo = $cuenta->codigo;
            $saldoInicial = $saldosIniciales[$cuenta->id] ?? $cuenta->saldo_inicial ?? 0;
            $cuentasSaldos[$codigo] = [
                'saldo_inicial' => (float) $saldoInicial,
                'debe' => $partidaDetalles[$id]->total_debe ?? 0,
                'haber' => $partidaDetalles[$id]->total_haber ?? 0,
            ];
        }

        foreach ($cuentasJerarquicas->sortByDesc('nivel') as $cuenta) {
            if ($cuenta->id_cuenta_padre && isset($idACodigo[$cuenta->id_cuenta_padre])) {
                $codigoPadre = $idACodigo[$cuenta->id_cuenta_padre];
                $cuentasSaldos[$codigoPadre]['saldo_inicial'] += $cuentasSaldos[$cuenta->codigo]['saldo_inicial'];
                $cuentasSaldos[$codigoPadre]['debe'] += $cuentasSaldos[$cuenta->codigo]['debe'];
                $cuentasSaldos[$codigoPadre]['haber'] += $cuentasSaldos[$cuenta->codigo]['haber'];
            }
        }

        $childrenOf = [];
        foreach ($cuentasJerarquicas as $c) {
            if ($c->id_cuenta_padre) {
                $childrenOf[$c->id_cuenta_padre] = true;
            }
        }

        $leafAccounts = $cuentasJerarquicas->filter(function (Cuenta $c) use ($childrenOf) {
            return empty($childrenOf[$c->id]);
        });

        foreach ($leafAccounts as $cuenta) {
            $codigo = $cuenta->codigo;
            $saldoInicial = $cuentasSaldos[$codigo]['saldo_inicial'] ?? 0;
            $debe = $cuentasSaldos[$codigo]['debe'] ?? 0;
            $haber = $cuentasSaldos[$codigo]['haber'] ?? 0;

            if ($cuenta->naturaleza === 'Deudor') {
                $saldoFinal = $saldoInicial + $debe - $haber;
            } else {
                $saldoFinal = $saldoInicial + $haber - $debe;
            }

            if (abs($saldoFinal) < 0.00001) {
                continue;
            }

            $this->distributeLeaf($cuenta, (float) $saldoFinal);
        }

        $utilidadCalculada = $this->computeUtilidadEjercicio($cuentasJerarquicas, $cuentasSaldos);
        if (abs($this->lines['utilidad_ejercicio']) < 0.01 && abs($utilidadCalculada) >= 0.01) {
            $this->lines['utilidad_ejercicio'] = $utilidadCalculada;
        }

        return $this->composeReport($endDate, $utilidadCalculada);
    }

    private function emptyLineKeys(): array
    {
        $keys = [
            'efectivo_equivalentes',
            'cuentas_cobrar_clientes',
            'documentos_cobrar',
            'provision_incobrables',
            'inventarios',
            'iva_credito_fiscal',
            'pago_cuenta_acumulado',
            'gastos_anticipados',
            'otros_activos_corrientes',
            'propiedad_planta_equipo',
            'depreciacion_acumulada',
            'activos_intangibles',
            'inversiones_largo_plazo',
            'activos_impuesto_diferido',
            'otros_activos_no_corrientes',
            'cuentas_pagar_proveedores',
            'prestamos_corto_plazo',
            'iva_debito_fiscal',
            'isr_por_pagar',
            'afp_por_pagar',
            'isss_por_pagar',
            'retenciones_isr_empleados',
            'otras_cuentas_pagar_corrientes',
            'prestamos_largo_plazo',
            'provision_indemnizaciones',
            'pasivos_impuesto_diferido',
            'otros_pasivos_no_corrientes',
            'capital_social',
            'reserva_legal',
            'utilidades_retenidas',
            'utilidad_ejercicio',
            'superavit_revaluacion',
        ];

        return array_fill_keys($keys, 0.0);
    }

    private function obtenerSaldosIniciales(Carbon $startDate, int $empresaId): array
    {
        $fechaInicioCarbon = Carbon::parse($startDate);
        $year = $fechaInicioCarbon->year;
        $month = $fechaInicioCarbon->month;

        $hayPeriodoAnterior = \App\Models\Contabilidad\SaldoMensual::where('id_empresa', $empresaId)
            ->where(function ($q) use ($year, $month) {
                $q->where('year', '<', $year)
                    ->orWhere(function ($q2) use ($year, $month) {
                        $q2->where('year', $year)->where('month', '<', $month);
                    });
            })
            ->exists();

        if (! $hayPeriodoAnterior) {
            return [];
        }

        $periodoAnterior = $month === 1
            ? ['year' => $year - 1, 'month' => 12]
            : ['year' => $year, 'month' => $month - 1];

        $saldosAnteriores = \App\Models\Contabilidad\SaldoMensual::where('year', $periodoAnterior['year'])
            ->where('month', $periodoAnterior['month'])
            ->where('id_empresa', $empresaId)
            ->get()
            ->keyBy('id_cuenta');

        $saldosIniciales = [];
        foreach ($saldosAnteriores as $saldo) {
            $saldosIniciales[$saldo->id_cuenta] = (float) ($saldo->saldo_final ?? 0);
        }

        return $saldosIniciales;
    }

    private function computeUtilidadEjercicio(Collection $cuentasJerarquicas, array $cuentasSaldos): float
    {
        $cuentasNivel0 = $cuentasJerarquicas->where('nivel', 0)->sortBy('codigo');
        $ingresos = 0.0;
        $costosGastos = 0.0;

        foreach ($cuentasNivel0 as $cuenta) {
            $codigo = $cuenta->codigo;
            $saldoInicial = $cuentasSaldos[$codigo]['saldo_inicial'] ?? 0;
            $debe = $cuentasSaldos[$codigo]['debe'] ?? 0;
            $haber = $cuentasSaldos[$codigo]['haber'] ?? 0;

            if ($cuenta->naturaleza === 'Deudor') {
                $saldoFinal = $saldoInicial + $debe - $haber;
            } else {
                $saldoFinal = $saldoInicial + $haber - $debe;
            }

            if (abs($saldoFinal) < 0.00001 || !$cuenta->rubro) {
                continue;
            }

            $rubro = strtolower(trim($cuenta->rubro));
            if (strpos($rubro, 'ingreso') !== false) {
                $ingresos += abs($saldoFinal);
            } elseif (strpos($rubro, 'costo') !== false || strpos($rubro, 'gasto') !== false) {
                $costosGastos += abs($saldoFinal);
            }
        }

        return $ingresos - $costosGastos;
    }

    private function distributeLeaf(Cuenta $cuenta, float $saldoFinal): void
    {
        $rubro = strtolower(trim((string) $cuenta->rubro));
        $haystack = $this->normalize($cuenta->nombre . ' ' . $cuenta->codigo . ' ' . $rubro);

        if ($this->isTemporaryResultAccount($haystack, $rubro)) {
            return;
        }

        if (strpos($rubro, 'ingreso') !== false || strpos($rubro, 'costo') !== false || strpos($rubro, 'gasto') !== false) {
            return;
        }

        if ($rubro === '') {
            if ($cuenta->naturaleza === 'Deudor') {
                $this->lines['otros_activos_corrientes'] += $saldoFinal;
            } else {
                $this->lines['otras_cuentas_pagar_corrientes'] += $this->presentationPasivo('otras_cuentas_pagar_corrientes', $cuenta, $saldoFinal);
            }

            return;
        }

        if (strpos($rubro, 'activo') !== false) {
            $key = $this->classifyActivo($haystack);
            $this->lines[$key] += $this->presentationActivo($key, $cuenta, $saldoFinal);

            return;
        }

        if (strpos($rubro, 'pasivo') !== false) {
            $key = $this->classifyPasivo($haystack);
            $this->lines[$key] += $this->presentationPasivo($key, $cuenta, $saldoFinal);

            return;
        }

        if (
            strpos($rubro, 'capital') !== false
            || strpos($rubro, 'patrimonio') !== false
            || strpos($rubro, 'resultado') !== false
        ) {
            if ($this->isUtilidadRetenidas($haystack)) {
                $this->lines['utilidades_retenidas'] += $saldoFinal;

                return;
            }
            if ($this->isReservaLegal($haystack)) {
                $this->lines['reserva_legal'] += $saldoFinal;

                return;
            }
            if ($this->isCapitalSocial($haystack)) {
                $this->lines['capital_social'] += $saldoFinal;

                return;
            }
            if ($this->isSuperavitRevaluacion($haystack)) {
                $this->lines['superavit_revaluacion'] += $saldoFinal;

                return;
            }
            if ($this->isUtilidadEjercicioAccount($haystack)) {
                $this->lines['utilidad_ejercicio'] += $saldoFinal;

                return;
            }

            $this->lines['utilidades_retenidas'] += $saldoFinal;
        }
    }

    private function isTemporaryResultAccount(string $haystack, string $rubro): bool
    {
        if (strpos($rubro, 'ingreso') !== false || strpos($rubro, 'gasto') !== false || strpos($rubro, 'costo') !== false) {
            return true;
        }

        return (bool) preg_match('/\b(cierra|cierre|sumaria|sumarias)\b/u', $haystack);
    }

    private function classifyActivo(string $haystack): string
    {
        $noCorriente = $this->isActivoNoCorriente($haystack);

        if ($noCorriente) {
            if (preg_match('/deprecia|amortiza.*acum|valor recuperable acumulado/u', $haystack)) {
                return 'depreciacion_acumulada';
            }
            if (preg_match('/intangib|plusvalia|plusvalía|patente|marca|licencia|software|nic\s*38/u', $haystack)) {
                return 'activos_intangibles';
            }
            if (preg_match('/impuesto diferido|diferido.*activo|nic\s*12.*activo|activo.*por impuesto diferido/u', $haystack)) {
                return 'activos_impuesto_diferido';
            }
            if (preg_match('/inversion|inversión|titulos|títulos|bonos|acciones|certificado/u', $haystack)
                && preg_match('/largo plazo|largo_plazo|no corriente|permanente/u', $haystack)) {
                return 'inversiones_largo_plazo';
            }
            if (preg_match('/propiedad|planta|equipo|maquinaria|vehiculo|vehículo|activo fijo|muebles|ensere|edificio|terreno|instalacion|instalación|arrendamiento.*derecho|uso de activo/u', $haystack)) {
                return 'propiedad_planta_equipo';
            }

            return 'otros_activos_no_corrientes';
        }

        if (preg_match('/deprecia|amortiza.*acum/u', $haystack)) {
            return 'depreciacion_acumulada';
        }

        if (preg_match('/documento.*cobrar|letra.*cobrar|pagare.*cobrar|pagaré.*cobrar|efectos.*cobrar/u', $haystack)) {
            return 'documentos_cobrar';
        }

        if (preg_match('/provision.*incobr|incobrable|dudoso|estima.*cobro|castigo/u', $haystack)) {
            return 'provision_incobrables';
        }

        if (preg_match('/inventario|mercaderia|mercadería|mercancia|mercancía|existencia|producto terminado|materia prima|peps|fifo|nic\s*2/u', $haystack)) {
            return 'inventarios';
        }

        if (preg_match('/iva.*credito|crédito fiscal|credito fiscal|iva.*recuper|iva.*favor|iva.*compras|anticipo.*iva/u', $haystack)) {
            return 'iva_credito_fiscal';
        }

        if (preg_match('/pago a cuenta|pagos a cuenta|anticipo.*renta|anticipo.*isr|isr.*anticipo|renta.*anticipo/u', $haystack)) {
            return 'pago_cuenta_acumulado';
        }

        if (preg_match('/anticipad|diferido|prepag|seguro.*pagado|alquiler.*pagado|interes.*pagado|interés.*pagado/u', $haystack)
            && ! preg_match('/largo plazo|no corriente/u', $haystack)) {
            return 'gastos_anticipados';
        }

        if (preg_match('/cobrar|cliente|deudor|cuenta.*por cobrar/u', $haystack)
            && ! preg_match('/proveedor|empleado|accionista|socio|prestamo|préstamo/u', $haystack)) {
            return 'cuentas_cobrar_clientes';
        }

        if (preg_match('/caja|banco|efectivo|equivalente|deposito|depósito|cheque|remesa|transferencia entrante/u', $haystack)) {
            return 'efectivo_equivalentes';
        }

        return 'otros_activos_corrientes';
    }

    private function classifyPasivo(string $haystack): string
    {
        $noCorriente = $this->isPasivoNoCorriente($haystack);

        if ($noCorriente) {
            if (preg_match('/prestamo|préstamo|financiamiento|obligacion|obligación|nota.*pagar|adeudo/u', $haystack)
                && preg_match('/largo plazo|largo_plazo|no corriente/u', $haystack)) {
                return 'prestamos_largo_plazo';
            }
            if (preg_match('/indemniza|prestacion|prestación|laboral|vacacion|aguinaldo|beneficio.*terminacion|terminación|art\.?\s*58/u', $haystack)) {
                return 'provision_indemnizaciones';
            }
            if (preg_match('/impuesto diferido|pasivo.*diferido|nic\s*12.*pasivo/u', $haystack)) {
                return 'pasivos_impuesto_diferido';
            }

            return 'otros_pasivos_no_corrientes';
        }

        if (preg_match('/afp|pension|pensión|ahorro.*previsional/u', $haystack)) {
            return 'afp_por_pagar';
        }
        if (preg_match('/isss|seguro social|cotizacion.*social|cotización.*social/u', $haystack)) {
            return 'isss_por_pagar';
        }
        if (preg_match('/retencion.*isr|retención.*isr|isr.*empleado|renta.*empleado.*reten/u', $haystack)) {
            return 'retenciones_isr_empleados';
        }
        if (preg_match('/iva.*debito|iva.*débito|iva.*por pagar|iva.*ventas|debito fiscal|débito fiscal/u', $haystack)) {
            return 'iva_debito_fiscal';
        }
        if (preg_match('/isr|impuesto.*renta|renta.*por pagar|pago.*definitivo.*renta/u', $haystack)
            && ! preg_match('/retencion|empleado|anticipo/u', $haystack)) {
            return 'isr_por_pagar';
        }
        if (preg_match('/prestamo|préstamo|linea|línea.*credito|crédito|sobregiro|tarjeta.*credito/u', $haystack)
            && preg_match('/corto|corto plazo|corriente|vence.*12|menor.*a.*1/u', $haystack)) {
            return 'prestamos_corto_plazo';
        }
        if (preg_match('/proveedor|cuenta.*por pagar|documento.*pagar|efectos.*pagar|acreedor.*operacion/u', $haystack)
            && ! preg_match('/largo plazo|no corriente/u', $haystack)) {
            return 'cuentas_pagar_proveedores';
        }

        return 'otras_cuentas_pagar_corrientes';
    }

    private function isActivoNoCorriente(string $haystack): bool
    {
        if (preg_match('/largo plazo|largo_plazo|no corriente|no_corriente|fijo|permanente|nic\s*16/u', $haystack)) {
            return true;
        }
        if (preg_match('/propiedad|planta|equipo|maquinaria|vehiculo|vehículo|activo fijo|intangib|deprecia|inversion.*largo|inversión.*largo/u', $haystack)) {
            return true;
        }

        return false;
    }

    private function isPasivoNoCorriente(string $haystack): bool
    {
        return (bool) preg_match('/largo plazo|largo_plazo|no corriente|no_corriente|indemniza|diferido.*pasivo|impuesto diferido.*pasivo/u', $haystack);
    }

    private function presentationActivo(string $key, Cuenta $cuenta, float $saldo): float
    {
        if (in_array($key, ['provision_incobrables', 'depreciacion_acumulada'], true) && $cuenta->naturaleza === 'Acreedor') {
            return -abs($saldo);
        }

        return $saldo;
    }

    private function presentationPasivo(string $key, Cuenta $cuenta, float $saldo): float
    {
        return $cuenta->naturaleza === 'Acreedor' ? abs($saldo) : $saldo;
    }

    private function isCapitalSocial(string $h): bool
    {
        return (bool) preg_match('/capital social|capital suscrito|aportacion|aportación.*social/u', $h);
    }

    private function isReservaLegal(string $h): bool
    {
        return (bool) preg_match('/reserva legal|reserva.*estatutaria/u', $h);
    }

    private function isUtilidadEjercicioAccount(string $h): bool
    {
        return (bool) preg_match('/utilidad.*ejercicio|resultado.*ejercicio|perdida.*ejercicio|pérdida.*ejercicio|resultado del periodo|resultado del período/u', $h);
    }

    private function isUtilidadRetenidas(string $h): bool
    {
        if ($this->isUtilidadEjercicioAccount($h)) {
            return false;
        }

        return (bool) preg_match('/retenid|acumulad|anterior|ejercicios anteriores|utilidad.*acum|resultados acumulados/u', $h);
    }

    private function isSuperavitRevaluacion(string $h): bool
    {
        return (bool) preg_match('/superavit|superávit|revalu|revalú|valoracion|valoración.*otro/u', $h);
    }

    private function normalize(string $text): string
    {
        $t = mb_strtolower(trim($text), 'UTF-8');

        return strtr($t, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ñ' => 'n', 'ü' => 'u',
        ]);
    }

    private function composeReport(Carbon $fechaCorte, float $utilidadCalculada): array
    {
        $L = $this->lines;

        $activoCorriente = $L['efectivo_equivalentes'] + $L['cuentas_cobrar_clientes'] + $L['documentos_cobrar']
            + $L['provision_incobrables'] + $L['inventarios'] + $L['iva_credito_fiscal']
            + $L['pago_cuenta_acumulado'] + $L['gastos_anticipados'] + $L['otros_activos_corrientes'];

        $activoNoCorriente = $L['propiedad_planta_equipo'] + $L['depreciacion_acumulada'] + $L['activos_intangibles']
            + $L['inversiones_largo_plazo'] + $L['activos_impuesto_diferido'] + $L['otros_activos_no_corrientes'];

        $totalActivos = $activoCorriente + $activoNoCorriente;

        $pasivoCorriente = $L['cuentas_pagar_proveedores'] + $L['prestamos_corto_plazo'] + $L['iva_debito_fiscal']
            + $L['isr_por_pagar'] + $L['afp_por_pagar'] + $L['isss_por_pagar']
            + $L['retenciones_isr_empleados'] + $L['otras_cuentas_pagar_corrientes'];

        $pasivoNoCorriente = $L['prestamos_largo_plazo'] + $L['provision_indemnizaciones']
            + $L['pasivos_impuesto_diferido'] + $L['otros_pasivos_no_corrientes'];

        $totalPasivos = $pasivoCorriente + $pasivoNoCorriente;

        $totalPatrimonio = $L['capital_social'] + $L['reserva_legal'] + $L['utilidades_retenidas']
            + $L['utilidad_ejercicio'] + $L['superavit_revaluacion'];

        $totalPasivosPatrimonio = $totalPasivos + $totalPatrimonio;

        $ecuacionCuadra = abs($totalActivos - $totalPasivosPatrimonio) < 0.02;

        $fechaLabel = $fechaCorte->translatedFormat('d \\d\\e F \\d\\e Y');

        return [
            'fecha_corte' => $fechaCorte->toDateString(),
            'fecha_corte_label' => $fechaLabel,
            'moneda' => 'USD',
            'nota_metodologia' => 'Inventarios: método PEPS (FIFO) según NIC 2, cuando aplica.',
            'activo_corriente' => $this->blockActivoCorriente($L, $activoCorriente),
            'activo_no_corriente' => $this->blockActivoNoCorriente($L, $activoNoCorriente),
            'pasivo_corriente' => $this->blockPasivoCorriente($L, $pasivoCorriente),
            'pasivo_no_corriente' => $this->blockPasivoNoCorriente($L, $pasivoNoCorriente),
            'patrimonio' => $this->blockPatrimonio($L, $totalPatrimonio),
            'totales' => [
                'activo_corriente' => $activoCorriente,
                'activo_no_corriente' => $activoNoCorriente,
                'activos' => $totalActivos,
                'pasivo_corriente' => $pasivoCorriente,
                'pasivo_no_corriente' => $pasivoNoCorriente,
                'pasivos' => $totalPasivos,
                'patrimonio' => $totalPatrimonio,
                'pasivos_mas_patrimonio' => $totalPasivosPatrimonio,
            ],
            'ecuacion_cuadra' => $ecuacionCuadra,
            'diferencia_ecuacion' => $totalActivos - $totalPasivosPatrimonio,
            'utilidad_ejercicio_computada' => $utilidadCalculada,
        ];
    }

    private function blockActivoCorriente(array $L, float $subtotal): array
    {
        return [
            'titulo' => 'Activo corriente',
            'lineas' => [
                ['clave' => 'efectivo_equivalentes', 'etiqueta' => 'Efectivo y equivalentes de efectivo', 'monto' => $L['efectivo_equivalentes']],
                ['clave' => 'cuentas_cobrar_clientes', 'etiqueta' => 'Cuentas por cobrar – clientes', 'monto' => $L['cuentas_cobrar_clientes']],
                ['clave' => 'documentos_cobrar', 'etiqueta' => 'Documentos por cobrar', 'monto' => $L['documentos_cobrar']],
                ['clave' => 'provision_incobrables', 'etiqueta' => 'Provisión para cuentas incobrables', 'monto' => $L['provision_incobrables']],
                ['clave' => 'inventarios', 'etiqueta' => 'Inventarios (PEPS / FIFO, NIC 2)', 'monto' => $L['inventarios']],
                ['clave' => 'iva_credito_fiscal', 'etiqueta' => 'Crédito fiscal IVA por recuperar', 'monto' => $L['iva_credito_fiscal']],
                ['clave' => 'pago_cuenta_acumulado', 'etiqueta' => 'Pago a cuenta acumulado', 'monto' => $L['pago_cuenta_acumulado']],
                ['clave' => 'gastos_anticipados', 'etiqueta' => 'Gastos pagados por anticipado', 'monto' => $L['gastos_anticipados']],
                ['clave' => 'otros_activos_corrientes', 'etiqueta' => 'Otros activos corrientes', 'monto' => $L['otros_activos_corrientes']],
            ],
            'total_etiqueta' => 'TOTAL ACTIVO CORRIENTE',
            'total' => $subtotal,
        ];
    }

    private function blockActivoNoCorriente(array $L, float $subtotal): array
    {
        return [
            'titulo' => 'Activo no corriente',
            'lineas' => [
                ['clave' => 'propiedad_planta_equipo', 'etiqueta' => 'Propiedad, planta y equipo (costo histórico)', 'monto' => $L['propiedad_planta_equipo']],
                ['clave' => 'depreciacion_acumulada', 'etiqueta' => 'Depreciación acumulada', 'monto' => $L['depreciacion_acumulada']],
                ['clave' => 'activos_intangibles', 'etiqueta' => 'Activos intangibles neto (NIC 38)', 'monto' => $L['activos_intangibles']],
                ['clave' => 'inversiones_largo_plazo', 'etiqueta' => 'Inversiones a largo plazo', 'monto' => $L['inversiones_largo_plazo']],
                ['clave' => 'activos_impuesto_diferido', 'etiqueta' => 'Activos por impuesto diferido (NIC 12)', 'monto' => $L['activos_impuesto_diferido']],
                ['clave' => 'otros_activos_no_corrientes', 'etiqueta' => 'Otros activos no corrientes', 'monto' => $L['otros_activos_no_corrientes']],
            ],
            'total_etiqueta' => 'TOTAL ACTIVO NO CORRIENTE',
            'total' => $subtotal,
        ];
    }

    private function blockPasivoCorriente(array $L, float $subtotal): array
    {
        return [
            'titulo' => 'Pasivo corriente',
            'lineas' => [
                ['clave' => 'cuentas_pagar_proveedores', 'etiqueta' => 'Cuentas por pagar – proveedores', 'monto' => $L['cuentas_pagar_proveedores']],
                ['clave' => 'prestamos_corto_plazo', 'etiqueta' => 'Préstamos bancarios corto plazo', 'monto' => $L['prestamos_corto_plazo']],
                ['clave' => 'iva_debito_fiscal', 'etiqueta' => 'IVA débito fiscal por pagar', 'monto' => $L['iva_debito_fiscal']],
                ['clave' => 'isr_por_pagar', 'etiqueta' => 'Impuesto sobre la Renta por pagar', 'monto' => $L['isr_por_pagar']],
                ['clave' => 'afp_por_pagar', 'etiqueta' => 'Cotizaciones AFP por pagar (empleado + patronal)', 'monto' => $L['afp_por_pagar']],
                ['clave' => 'isss_por_pagar', 'etiqueta' => 'Cuotas ISSS por pagar', 'monto' => $L['isss_por_pagar']],
                ['clave' => 'retenciones_isr_empleados', 'etiqueta' => 'Retenciones ISR empleados por pagar', 'monto' => $L['retenciones_isr_empleados']],
                ['clave' => 'otras_cuentas_pagar_corrientes', 'etiqueta' => 'Otras cuentas por pagar corrientes', 'monto' => $L['otras_cuentas_pagar_corrientes']],
            ],
            'total_etiqueta' => 'TOTAL PASIVO CORRIENTE',
            'total' => $subtotal,
        ];
    }

    private function blockPasivoNoCorriente(array $L, float $subtotal): array
    {
        return [
            'titulo' => 'Pasivo no corriente',
            'lineas' => [
                ['clave' => 'prestamos_largo_plazo', 'etiqueta' => 'Préstamos bancarios largo plazo', 'monto' => $L['prestamos_largo_plazo']],
                ['clave' => 'provision_indemnizaciones', 'etiqueta' => 'Provisión para indemnizaciones (Art. 58 Código de Trabajo SV)', 'monto' => $L['provision_indemnizaciones']],
                ['clave' => 'pasivos_impuesto_diferido', 'etiqueta' => 'Pasivos por impuesto diferido', 'monto' => $L['pasivos_impuesto_diferido']],
                ['clave' => 'otros_pasivos_no_corrientes', 'etiqueta' => 'Otros pasivos no corrientes', 'monto' => $L['otros_pasivos_no_corrientes']],
            ],
            'total_etiqueta' => 'TOTAL PASIVO NO CORRIENTE',
            'total' => $subtotal,
        ];
    }

    private function blockPatrimonio(array $L, float $subtotal): array
    {
        return [
            'titulo' => 'Patrimonio',
            'lineas' => [
                ['clave' => 'capital_social', 'etiqueta' => 'Capital social', 'monto' => $L['capital_social']],
                ['clave' => 'reserva_legal', 'etiqueta' => 'Reserva legal (7%–20% capital — Art. 123 Código de Comercio SV)', 'monto' => $L['reserva_legal']],
                ['clave' => 'utilidades_retenidas', 'etiqueta' => 'Utilidades retenidas de ejercicios anteriores', 'monto' => $L['utilidades_retenidas']],
                ['clave' => 'utilidad_ejercicio', 'etiqueta' => 'Utilidad (pérdida) del ejercicio', 'monto' => $L['utilidad_ejercicio']],
                ['clave' => 'superavit_revaluacion', 'etiqueta' => 'Superávit por revaluación de activos', 'monto' => $L['superavit_revaluacion']],
            ],
            'total_etiqueta' => 'TOTAL PATRIMONIO',
            'total' => $subtotal,
        ];
    }
}
