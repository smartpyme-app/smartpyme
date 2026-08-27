<!DOCTYPE html>
<html>
<head>
    <title>Estado de resultados</title>
    <style>
        * { font-size: 10px; margin: 0; padding: 0; }
        html, body { font-family: Arial, Helvetica, sans-serif; }
        #er { margin: 0.8cm; }
        .header { text-align: center; margin-bottom: 14px; }
        .logo { max-height: 64px; max-width: 180px; margin-bottom: 8px; }
        .header h1 { font-size: 15px; margin-bottom: 4px; }
        .header h2 { font-size: 13px; font-weight: bold; margin-bottom: 4px; }
        .header .sub { font-size: 10px; margin-bottom: 2px; }
        .nota { font-size: 8px; color: #333; font-style: italic; margin: 4px 0 10px 0; }
        table.cascada { width: 100%; border-collapse: collapse; }
        table.cascada td { padding: 3px 4px; vertical-align: top; }
        .label { width: 72%; text-align: left; }
        .cur { width: 14%; text-align: right; }
        .ant { width: 14%; text-align: right; }
        .th { font-weight: bold; background: #e2e8f0; border-bottom: 1px solid #000; }
        .seccion { font-weight: bold; margin-top: 10px; margin-bottom: 4px; font-size: 11px; }
        .subt { font-weight: bold; border-top: 1px solid #000; padding-top: 4px; }
        .doble { font-weight: bold; border-top: 2px solid #000; border-bottom: 2px double #000; margin-top: 4px; font-size: 11px; }
        .kpi th { text-align: left; font-size: 10px; }
        .kpi td { text-align: right; font-size: 10px; }
    </style>
</head>
<body>
@php
    $P = \App\Services\Contabilidad\EstadoResultadosNiifSvPresenter::class;
    $c = $estado['cascada'] ?? [];
    $mc = (bool) ($estado['mostrar_comparativa'] ?? false);
    $a = $mc ? ($estado['comparativa']['anterior']['cascada'] ?? null) : null;
    $span = $mc ? 4 : 2;
    $fmtN = function ($n) {
        if ($n === null) { return '—'; }
        $n = (float) $n;
        if (abs($n) < 0.0005) { return '—'; }
        return number_format($n, 2);
    };
    $fmtP = function ($n) {
        if ($n === null) { return '—'; }
        return number_format($n * 100, 2) . ' %';
    };
    $g = function ($ar, $k) {
        if (!$ar || !array_key_exists($k, $ar)) { return null; }
        return (float) $ar[$k];
    };
    $varP = function ($act, $prev) {
        if ($prev === null || $act === null) { return '—'; }
        if (abs($prev) < 0.0001) { return '—'; }
        return number_format((($act - $prev) / $prev) * 100, 1) . ' %';
    };
    $logoSrc = null;
    if (!empty($empresa->logo)) {
        $logoRel = ltrim(str_replace('\\', '/', (string) $empresa->logo), '/');
        if ($logoRel !== '' && strpos($logoRel, '..') === false) {
            $fullLogo = public_path('img' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $logoRel));
            if (is_file($fullLogo)) {
                $mime = null;
                if (function_exists('finfo_open')) {
                    $fi = finfo_open(FILEINFO_MIME_TYPE);
                    if ($fi) { $mime = finfo_file($fi, $fullLogo) ?: null; finfo_close($fi); }
                }
                if (! $mime && function_exists('mime_content_type')) {
                    $mime = @mime_content_type($fullLogo) ?: null;
                }
                if ($mime && strpos($mime, 'image/') === 0) {
                    $logoSrc = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($fullLogo));
                }
            }
        }
    }
@endphp

<section id="er">
    <div class="header">
        @if($logoSrc)
            <img class="logo" src="{{ $logoSrc }}" alt="">
        @endif
        <h1>{{ $empresa->nombre }}</h1>
        <h2>ESTADO DE RENDIMIENTO FINANCIERO (ESTADO DE RESULTADOS)</h2>
        <p class="sub">Expresado en USD (sin IVA en ingresos/costos operativos)</p>
        <p class="sub">{{ $estado['periodo_titulo'] ?? '' }}</p>
        @if($mc && !empty($estado['comparativa']['periodo_anterior_titulo'] ?? ''))
            <p class="sub">Comparado con: {{ $estado['comparativa']['periodo_anterior_titulo'] }}</p>
        @endif
    </div>
    <p class="nota">Nota: el IVA 13% es cuenta de balance; no se muestra en este estado. Las tasas fiscal y de reserva son estimaciones; el Excel incluye celdas editables en la hoja Parámetros. @if(!$mc)Sólo se muestran cifras del rango de fechas elegido; para comparar con un período anterior, active la opción al generar el reporte desde partidas.@endif</p>

    <table class="cascada">
        <tr class="th">
            <td class="label">Concepto</td>
            @if($mc)
            <td class="cur">Período actual</td>
            <td class="ant">Período ant.</td>
            <td class="ant" style="width:10%;">Var. %</td>
            @else
            <td class="cur">Importe</td>
            @endif
        </tr>
        <tr><td colspan="{{ $span }}" class="seccion">INGRESOS DE OPERACIÓN</td></tr>
        <tr><td class="label">Ventas brutas (sin IVA)</td><td class="cur">{{ $fmtN($g($c,'ventas_brutas')) }}</td>@if($mc)<td class="ant">{{ $fmtN($a ? $g($a,'ventas_brutas') : null) }}</td><td class="ant">{{ $varP($g($c,'ventas_brutas'), $a ? $g($a,'ventas_brutas') : null) }}</td>@endif</tr>
        <tr><td class="label">Menos: devoluciones sobre ventas</td><td class="cur">{{ $fmtN($g($c,'devoluciones_ventas')) }}</td>@if($mc)<td class="ant">{{ $fmtN($a ? $g($a,'devoluciones_ventas') : null) }}</td><td class="ant">{{ $varP($g($c,'devoluciones_ventas'), $a ? $g($a,'devoluciones_ventas') : null) }}</td>@endif</tr>
        <tr><td class="label">Menos: descuentos sobre ventas</td><td class="cur">{{ $fmtN($g($c,'descuentos_ventas')) }}</td>@if($mc)<td class="ant">{{ $fmtN($a ? $g($a,'descuentos_ventas') : null) }}</td><td class="ant">{{ $varP($g($c,'descuentos_ventas'), $a ? $g($a,'descuentos_ventas') : null) }}</td>@endif</tr>
        <tr class="subt"><td class="label">{{ $P::LBL_VENTAS_NETAS }}</td><td class="cur">{{ $fmtN($g($c, $P::LBL_VENTAS_NETAS)) }}</td>@if($mc)<td class="ant">{{ $fmtN($a ? $g($a, $P::LBL_VENTAS_NETAS) : null) }}</td><td class="ant">{{ $varP($g($c, $P::LBL_VENTAS_NETAS), $a ? $g($a, $P::LBL_VENTAS_NETAS) : null) }}</td>@endif</tr>

        <tr><td colspan="{{ $span }}" class="seccion">COSTO DE VENTAS (PEPS / FIFO, NIC 2 — cuando aplica)</td></tr>
        <tr><td class="label">Inventario inicial de mercaderías</td><td class="cur">{{ $fmtN($g($c,'inventario_inicial')) }}</td>@if($mc)<td class="ant">{{ $fmtN($a ? $g($a,'inventario_inicial') : null) }}</td><td class="ant">{{ $varP($g($c,'inventario_inicial'), $a ? $g($a,'inventario_inicial') : null) }}</td>@endif</tr>
        <tr><td class="label">Compras brutas del período</td><td class="cur">{{ $fmtN($g($c,'compras_brutas')) }}</td>@if($mc)<td class="ant">{{ $fmtN($a ? $g($a,'compras_brutas') : null) }}</td><td class="ant">{{ $varP($g($c,'compras_brutas'), $a ? $g($a,'compras_brutas') : null) }}</td>@endif</tr>
        <tr><td class="label">Fletes y seguros sobre compras</td><td class="cur">{{ $fmtN($g($c,'fletes_compras')) }}</td>@if($mc)<td class="ant">{{ $fmtN($a ? $g($a,'fletes_compras') : null) }}</td><td class="ant">{{ $varP($g($c,'fletes_compras'), $a ? $g($a,'fletes_compras') : null) }}</td>@endif</tr>
        <tr><td class="label">Menos: devoluciones sobre compras</td><td class="cur">{{ $fmtN($g($c,'devoluciones_compras')) }}</td>@if($mc)<td class="ant">{{ $fmtN($a ? $g($a,'devoluciones_compras') : null) }}</td><td class="ant">{{ $varP($g($c,'devoluciones_compras'), $a ? $g($a,'devoluciones_compras') : null) }}</td>@endif</tr>
        <tr><td class="label">Menos: descuentos sobre compras</td><td class="cur">{{ $fmtN($g($c,'descuentos_compras')) }}</td>@if($mc)<td class="ant">{{ $fmtN($a ? $g($a,'descuentos_compras') : null) }}</td><td class="ant">{{ $varP($g($c,'descuentos_compras'), $a ? $g($a,'descuentos_compras') : null) }}</td>@endif</tr>
        <tr><td class="label">Menos: inventario final de mercaderías</td><td class="cur">{{ $fmtN($g($c,'inventario_final')) }}</td>@if($mc)<td class="ant">{{ $fmtN($a ? $g($a,'inventario_final') : null) }}</td><td class="ant">{{ $varP($g($c,'inventario_final'), $a ? $g($a,'inventario_final') : null) }}</td>@endif</tr>
        <tr class="subt"><td class="label">{{ $P::LBL_COGS }}</td><td class="cur">{{ $fmtN($g($c, $P::LBL_COGS)) }}</td>@if($mc)<td class="ant">{{ $fmtN($a ? $g($a, $P::LBL_COGS) : null) }}</td><td class="ant">{{ $varP($g($c, $P::LBL_COGS), $a ? $g($a, $P::LBL_COGS) : null) }}</td>@endif</tr>
        <tr class="subt"><td class="label">{{ $P::LBL_UTILIDAD_BRUTA }}</td><td class="cur">{{ $fmtN($g($c, $P::LBL_UTILIDAD_BRUTA)) }}</td>@if($mc)<td class="ant">{{ $fmtN($a ? $g($a, $P::LBL_UTILIDAD_BRUTA) : null) }}</td><td class="ant">{{ $varP($g($c, $P::LBL_UTILIDAD_BRUTA), $a ? $g($a, $P::LBL_UTILIDAD_BRUTA) : null) }}</td>@endif</tr>

        <tr><td colspan="{{ $span }}" class="seccion">GASTOS DE VENTA</td></tr>
        @foreach(($c['gastos_venta_lineas'] ?? []) as $line)
        <tr>
            <td class="label" style="padding-left:8px;">{{ $line['etiqueta'] ?? '' }}</td>
            <td class="cur">{{ $fmtN($c['gastos_venta_detalle'][$line['k']] ?? 0) }}</td>
            @if($mc)
            <td class="ant">{{ $a ? $fmtN($a['gastos_venta_detalle'][$line['k']] ?? 0) : '—' }}</td>
            <td class="ant">{{ $a ? $varP($c['gastos_venta_detalle'][$line['k']] ?? 0, $a['gastos_venta_detalle'][$line['k']] ?? 0) : '—' }}</td>
            @endif
        </tr>
        @endforeach
        <tr class="subt">
            <td class="label">TOTAL GASTOS DE VENTA</td>
            <td class="cur">{{ $fmtN($c['total_gastos_venta'] ?? 0) }}</td>
            @if($mc)
            <td class="ant">{{ $a ? $fmtN($a['total_gastos_venta'] ?? 0) : '—' }}</td>
            <td class="ant">{{ $a ? $varP($c['total_gastos_venta'] ?? 0, $a['total_gastos_venta'] ?? 0) : '—' }}</td>
            @endif
        </tr>

        <tr><td colspan="{{ $span }}" class="seccion">GASTOS DE ADMINISTRACIÓN</td></tr>
        @foreach(($c['gastos_admin_lineas'] ?? []) as $line)
        <tr>
            <td class="label" style="padding-left:8px;">{{ $line['etiqueta'] ?? '' }}</td>
            <td class="cur">{{ $fmtN($c['gastos_admin_detalle'][$line['k']] ?? 0) }}</td>
            @if($mc)
            <td class="ant">{{ $a ? $fmtN($a['gastos_admin_detalle'][$line['k']] ?? 0) : '—' }}</td>
            <td class="ant">{{ $a ? $varP($c['gastos_admin_detalle'][$line['k']] ?? 0, $a['gastos_admin_detalle'][$line['k']] ?? 0) : '—' }}</td>
            @endif
        </tr>
        @endforeach
        <tr class="subt">
            <td class="label">TOTAL GASTOS DE ADMINISTRACIÓN</td>
            <td class="cur">{{ $fmtN($c['total_gastos_admin'] ?? 0) }}</td>
            @if($mc)
            <td class="ant">{{ $a ? $fmtN($a['total_gastos_admin'] ?? 0) : '—' }}</td>
            <td class="ant">{{ $a ? $varP($c['total_gastos_admin'] ?? 0, $a['total_gastos_admin'] ?? 0) : '—' }}</td>
            @endif
        </tr>
        <tr class="subt"><td class="label">{{ $P::LBL_TOT_GASTOS_OP }}</td><td class="cur">{{ $fmtN($g($c, $P::LBL_TOT_GASTOS_OP)) }}</td>@if($mc)<td class="ant">{{ $fmtN($a ? $g($a, $P::LBL_TOT_GASTOS_OP) : null) }}</td><td class="ant">{{ $varP($g($c, $P::LBL_TOT_GASTOS_OP), $a ? $g($a, $P::LBL_TOT_GASTOS_OP) : null) }}</td>@endif</tr>
        <tr class="subt"><td class="label">{{ $P::LBL_UTILIDAD_OP }}</td><td class="cur">{{ $fmtN($g($c, $P::LBL_UTILIDAD_OP)) }}</td>@if($mc)<td class="ant">{{ $fmtN($a ? $g($a, $P::LBL_UTILIDAD_OP) : null) }}</td><td class="ant">{{ $varP($g($c, $P::LBL_UTILIDAD_OP), $a ? $g($a, $P::LBL_UTILIDAD_OP) : null) }}</td>@endif</tr>

        <tr><td colspan="{{ $span }}" class="seccion">OTROS INGRESOS Y GASTOS (NO OPERATIVO / FINANCIERO)</td></tr>
        <tr><td class="label">Total otros ingresos</td><td class="cur">{{ $fmtN($c['otros_ing'] ?? 0) }}</td>@if($mc)<td class="ant">{{ $a ? $fmtN($a['otros_ing'] ?? 0) : '—' }}</td><td class="ant">{{ $a ? $varP($c['otros_ing'] ?? 0, $a['otros_ing'] ?? 0) : '—' }}</td>@endif</tr>
        <tr><td class="label">Total otros gastos</td><td class="cur">{{ $fmtN($c['otros_gas'] ?? 0) }}</td>@if($mc)<td class="ant">{{ $a ? $fmtN($a['otros_gas'] ?? 0) : '—' }}</td><td class="ant">{{ $a ? $varP($c['otros_gas'] ?? 0, $a['otros_gas'] ?? 0) : '—' }}</td>@endif</tr>
        <tr class="subt"><td class="label">{{ $P::LBL_TOT_OTROS }}</td><td class="cur">{{ $fmtN($g($c, $P::LBL_TOT_OTROS)) }}</td>@if($mc)<td class="ant">{{ $fmtN($a ? $g($a, $P::LBL_TOT_OTROS) : null) }}</td><td class="ant">{{ $varP($g($c, $P::LBL_TOT_OTROS), $a ? $g($a, $P::LBL_TOT_OTROS) : null) }}</td>@endif</tr>
        <tr class="subt"><td class="label">{{ $P::LBL_UTIL_ANTES_RES }}</td><td class="cur">{{ $fmtN($g($c, $P::LBL_UTIL_ANTES_RES)) }}</td>@if($mc)<td class="ant">{{ $fmtN($a ? $g($a, $P::LBL_UTIL_ANTES_RES) : null) }}</td><td class="ant">{{ $varP($g($c, $P::LBL_UTIL_ANTES_RES), $a ? $g($a, $P::LBL_UTIL_ANTES_RES) : null) }}</td>@endif</tr>
        <tr><td class="label">{{ $P::LBL_RESERVA }}</td><td class="cur">{{ $fmtN($c['reserva_legal'] ?? 0) }}</td>@if($mc)<td class="ant">{{ $a ? $fmtN($a['reserva_legal'] ?? 0) : '—' }}</td><td class="ant">{{ $a ? $varP($c['reserva_legal'] ?? 0, $a['reserva_legal'] ?? 0) : '—' }}</td>@endif</tr>
        <tr class="subt"><td class="label">{{ $P::LBL_UTIL_ANTES_ISR }}</td><td class="cur">{{ $fmtN($g($c, $P::LBL_UTIL_ANTES_ISR)) }}</td>@if($mc)<td class="ant">{{ $fmtN($a ? $g($a, $P::LBL_UTIL_ANTES_ISR) : null) }}</td><td class="ant">{{ $varP($g($c, $P::LBL_UTIL_ANTES_ISR), $a ? $g($a, $P::LBL_UTIL_ANTES_ISR) : null) }}</td>@endif</tr>
        <tr><td class="label">Tasa ISR (25% o 30% según proyección anual de ingresos)</td><td class="cur">{{ $fmtP($c['isr_tasa'] ?? 0) }}</td>@if($mc)<td class="ant">{{ $a ? $fmtP($a['isr_tasa'] ?? 0) : '—' }}</td><td class="ant">—</td>@endif</tr>
        <tr><td class="label">{{ $P::LBL_ISR_EST }}</td><td class="cur">{{ $fmtN($c['isr_estimado'] ?? 0) }}</td>@if($mc)<td class="ant">{{ $a ? $fmtN($a['isr_estimado'] ?? 0) : '—' }}</td><td class="ant">{{ $a ? $varP($c['isr_estimado'] ?? 0, $a['isr_estimado'] ?? 0) : '—' }}</td>@endif</tr>
        <tr><td class="label">{{ $P::LBL_BASE_PAGO }}</td><td class="cur">{{ $fmtN($c['base_ingresos_brutos'] ?? 0) }}</td>@if($mc)<td class="ant">{{ $a ? $fmtN($a['base_ingresos_brutos'] ?? 0) : '—' }}</td><td class="ant">{{ $a ? $varP($c['base_ingresos_brutos'] ?? 0, $a['base_ingresos_brutos'] ?? 0) : '—' }}</td>@endif</tr>
        <tr><td class="label">{{ $P::LBL_PAGO_CTA }}</td><td class="cur">{{ $fmtN($c['pago_cuenta'] ?? 0) }}</td>@if($mc)<td class="ant">{{ $a ? $fmtN($a['pago_cuenta'] ?? 0) : '—' }}</td><td class="ant">{{ $a ? $varP($c['pago_cuenta'] ?? 0, $a['pago_cuenta'] ?? 0) : '—' }}</td>@endif</tr>
        <tr class="subt"><td class="label">{{ $P::LBL_ISR_NETO }}</td><td class="cur">{{ $fmtN($c['isr_neto'] ?? 0) }}</td>@if($mc)<td class="ant">{{ $a ? $fmtN($a['isr_neto'] ?? 0) : '—' }}</td><td class="ant">{{ $a ? $varP($c['isr_neto'] ?? 0, $a['isr_neto'] ?? 0) : '—' }}</td>@endif</tr>
        <tr class="doble"><td class="label">{{ $P::LBL_UTIL_NETA }}</td><td class="cur">{{ $fmtN($g($c, $P::LBL_UTIL_NETA)) }}</td>@if($mc)<td class="ant">{{ $fmtN($a ? $g($a, $P::LBL_UTIL_NETA) : null) }}</td><td class="ant">{{ $varP($g($c, $P::LBL_UTIL_NETA), $a ? $g($a, $P::LBL_UTIL_NETA) : null) }}</td>@endif</tr>
    </table>

    @php $kpi = $estado['kpi'] ?? []; @endphp
    <p class="seccion" style="margin-top:12px;">Indicadores (sobre {{ $P::LBL_VENTAS_NETAS }})</p>
    <table class="kpi cascada" style="max-width: 92%;">
        <tr><th class="label">Margen bruto</th><td>{{ $fmtP($kpi['margen_bruto'] ?? null) }}</td></tr>
        <tr><th class="label">Margen operativo</th><td>{{ $fmtP($kpi['margen_operativo'] ?? null) }}</td></tr>
        <tr><th class="label">Margen neto</th><td>{{ $fmtP($kpi['margen_neto'] ?? null) }}</td></tr>
        @if($mc)
        <tr><th class="label">Crecimiento de ventas netas (frente a período anterior inmediato)</th><td>{{ $fmtP($kpi['crec_ventas'] ?? null) }}</td></tr>
        @endif
        <tr><th class="label">Carga fiscal (ISR est. / utilidad antes ISR)</th><td>{{ $fmtP($kpi['carga_fiscal_isr'] ?? null) }}</td></tr>
        <tr><th class="label">Costo de ventas / ventas netas</th><td>{{ $fmtP($kpi['costo_ventas_pct'] ?? null) }}</td></tr>
    </table>
</section>
</body>
</html>
