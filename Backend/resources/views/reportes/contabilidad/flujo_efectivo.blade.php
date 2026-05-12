<!DOCTYPE html>
<html>
<head>
    <title>Flujo de efectivo</title>
    <style>
        * { font-size: 10px; margin: 0; padding: 0; }
        html, body { font-family: Arial, Helvetica, sans-serif; }
        #efe { margin: 0.8cm; }
        .header { text-align: center; margin-bottom: 14px; }
        .logo { max-height: 64px; max-width: 180px; margin-bottom: 8px; }
        .header h1 { font-size: 15px; margin-bottom: 4px; }
        .header h2 { font-size: 12px; font-weight: bold; margin-bottom: 4px; }
        .header .sub { font-size: 10px; margin-bottom: 2px; }
        .nota { font-size: 8px; color: #333; font-style: italic; margin: 4px 0 10px 0; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.data td { padding: 3px 4px; vertical-align: top; }
        .label { width: 58%; text-align: left; }
        .num { width: 14%; text-align: right; }
        .th { font-weight: bold; background: #e2e8f0; border-bottom: 1px solid #000; }
        .seccion { font-weight: bold; margin-top: 10px; margin-bottom: 4px; font-size: 11px; }
        .subt { font-weight: bold; border-top: 1px solid #000; padding-top: 4px; }
    </style>
</head>
<body>
@php
    $mc = (bool) ($flujo['mostrar_comparativa'] ?? false);
    $A = $flujo['actual'] ?? [];
    $B = $mc ? ($flujo['anterior'] ?? []) : [];
    $span = $mc ? 4 : 2;
    $fmtN = function ($n) {
        if ($n === null) { return '—'; }
        $n = (float) $n;
        if (abs($n) < 0.0005) { return '—'; }
        return number_format($n, 2);
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
    $rowDet = function ($det, $detB, $useMc) use ($fmtN) {
        $out = '';
        foreach ($det as $line) {
            $lab = $line['etiqueta'] ?? $line['clave'] ?? '';
            $m = (float) ($line['monto'] ?? 0);
            $clave = (string) ($line['clave'] ?? '');
            $mAnt = 0.0;
            if ($useMc && is_array($detB)) {
                foreach ($detB as $lb) {
                    if (($lb['clave'] ?? '') === $clave) {
                        $mAnt = (float) ($lb['monto'] ?? 0);
                        break;
                    }
                }
            }
            $out .= '<tr><td class="label">' . e($lab) . '</td>';
            $out .= '<td class="num">' . $fmtN($m) . '</td>';
            if ($useMc) {
                $out .= '<td class="num">' . $fmtN($mAnt) . '</td>';
                $out .= '<td class="num">' . $fmtN($m - $mAnt) . '</td>';
            }
            $out .= '</tr>';
        }
        return $out;
    };
@endphp

<section id="efe">
    <div class="header">
        @if($logoSrc)
            <img class="logo" src="{{ $logoSrc }}" alt="">
        @endif
        <h1>{{ $empresa->nombre }}</h1>
        <h2>ESTADO DE FLUJOS DE EFECTIVO</h2>
        <p class="sub">Método indirecto con conciliación de efectivo — USD</p>
        <p class="sub">{{ $flujo['periodo_actual']['titulo'] ?? '' }}</p>
        @if($mc && !empty($flujo['periodo_anterior']['titulo'] ?? ''))
            <p class="sub">Comparado con: {{ $flujo['periodo_anterior']['titulo'] }}</p>
        @endif
    </div>
    <p class="nota">
        La utilidad inicial corresponde a la utilidad neta (estimada) del estado de resultados NIIF del mismo rango.
        En v1 no se incluye la variación de la cuenta patrimonial «utilidad del ejercicio» en financiación.
        Revise el catálogo de cuentas si hay descuadres o partidas en «Otros» del balance.
    </p>

    @php
        $bloques = [
            ['titulo' => 'ACTIVIDADES OPERATIVAS', 'key' => 'operacion'],
            ['titulo' => 'ACTIVIDADES DE INVERSIÓN', 'key' => 'inversion'],
            ['titulo' => 'ACTIVIDADES DE FINANCIACIÓN', 'key' => 'financiacion'],
        ];
    @endphp
    @foreach($bloques as $bloque)
        @php
            $sec = $A[$bloque['key']] ?? null;
        @endphp
        @if(is_array($sec))
        @php
            $det = $sec['detalle'] ?? [];
            $detB = $mc ? ($B[$bloque['key']]['detalle'] ?? []) : [];
        @endphp
        <p class="seccion">{{ $bloque['titulo'] }}</p>
        <table class="data">
            <tr class="th">
                <td class="label">Concepto</td>
                @if($mc)
                <td class="num">Actual</td>
                <td class="num">Anterior</td>
                <td class="num">Dif.</td>
                @else
                <td class="num">Importe</td>
                @endif
            </tr>
            {!! $rowDet($det, $detB, $mc) !!}
            <tr class="subt">
                <td class="label">Total</td>
                <td class="num">{{ $fmtN($sec['total'] ?? 0) }}</td>
                @if($mc)
                <td class="num">{{ $fmtN($B[$bloque['key']]['total'] ?? 0) }}</td>
                <td class="num">{{ $fmtN(($sec['total'] ?? 0) - ($B[$bloque['key']]['total'] ?? 0)) }}</td>
                @endif
            </tr>
        </table>
        @endif
    @endforeach

    <p class="seccion" style="margin-top:12px;">Efectivo y equivalentes (línea balance NIIF)</p>
    <table class="data">
        <tr class="th">
            <td class="label">Concepto</td>
            @if($mc)<td class="num">Actual</td><td class="num">Anterior</td><td class="num">Dif.</td>
            @else<td class="num">Importe</td>@endif
        </tr>
        @php $e = $A['efectivo'] ?? []; $e2 = $mc ? ($B['efectivo'] ?? []) : []; @endphp
        <tr><td class="label">Saldo línea al inicio del periodo analizado</td><td class="num">{{ $fmtN($e['saldo_linea_inicio'] ?? 0) }}</td>
            @if($mc)<td class="num">{{ $fmtN($e2['saldo_linea_inicio'] ?? 0) }}</td><td class="num">{{ $fmtN(($e['saldo_linea_inicio']??0)-($e2['saldo_linea_inicio']??0)) }}</td>@endif</tr>
        <tr><td class="label">Saldo línea al fin del periodo</td><td class="num">{{ $fmtN($e['saldo_linea_fin'] ?? 0) }}</td>
            @if($mc)<td class="num">{{ $fmtN($e2['saldo_linea_fin'] ?? 0) }}</td><td class="num">{{ $fmtN(($e['saldo_linea_fin']??0)-($e2['saldo_linea_fin']??0)) }}</td>@endif</tr>
        <tr class="subt"><td class="label">Variación línea efectivo</td><td class="num">{{ $fmtN($e['variacion_linea'] ?? 0) }}</td>
            @if($mc)<td class="num">{{ $fmtN($e2['variacion_linea'] ?? 0) }}</td><td class="num">{{ $fmtN(($e['variacion_linea']??0)-($e2['variacion_linea']??0)) }}</td>@endif</tr>
    </table>

    <p class="seccion">Conciliación</p>
    @php $c = $A['conciliacion'] ?? []; $c2 = $mc ? ($B['conciliacion'] ?? []) : []; @endphp
    <table class="data">
        <tr class="th">
            <td class="label">Concepto</td>
            @if($mc)<td class="num">Actual</td><td class="num">Anterior</td><td class="num">Dif.</td>
            @else<td class="num">Importe</td>@endif
        </tr>
        <tr><td class="label">Flujo indirecto neto (operación + inversión + financiación)</td><td class="num">{{ $fmtN($c['flujo_indirecto_neto'] ?? 0) }}</td>
            @if($mc)<td class="num">{{ $fmtN($c2['flujo_indirecto_neto'] ?? 0) }}</td><td class="num">{{ $fmtN(($c['flujo_indirecto_neto']??0)-($c2['flujo_indirecto_neto']??0)) }}</td>@endif</tr>
        <tr><td class="label">Variación efectivo según balance</td><td class="num">{{ $fmtN($c['variacion_efectivo_balance'] ?? 0) }}</td>
            @if($mc)<td class="num">{{ $fmtN($c2['variacion_efectivo_balance'] ?? 0) }}</td><td class="num">{{ $fmtN(($c['variacion_efectivo_balance']??0)-($c2['variacion_efectivo_balance']??0)) }}</td>@endif</tr>
        <tr class="subt"><td class="label">Diferencia (revisar partidas y catálogo)</td><td class="num">{{ $fmtN($c['diferencia'] ?? 0) }}</td>
            @if($mc)<td class="num">{{ $fmtN($c2['diferencia'] ?? 0) }}</td><td class="num">{{ $fmtN(($c['diferencia']??0)-($c2['diferencia']??0)) }}</td>@endif</tr>
    </table>
</section>
</body>
</html>
