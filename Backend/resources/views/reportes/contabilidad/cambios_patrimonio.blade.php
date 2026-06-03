<!DOCTYPE html>
<html>
<head>
    <title>Cambios en el patrimonio</title>
    <style>
        * { font-size: 9px; margin: 0; padding: 0; }
        html, body { font-family: Arial, Helvetica, sans-serif; }
        #ecp { margin: 0.6cm; }
        .header { text-align: center; margin-bottom: 12px; }
        .logo { max-height: 60px; max-width: 170px; margin-bottom: 6px; }
        .header h1 { font-size: 14px; margin-bottom: 3px; }
        .header h2 { font-size: 11px; font-weight: bold; margin-bottom: 3px; }
        .header .sub { font-size: 9px; margin-bottom: 2px; }
        .nota { font-size: 8px; color: #333; font-style: italic; margin: 4px 0 8px 0; }
        table.matriz { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.matriz th, table.matriz td { padding: 3px 4px; border-bottom: 1px solid #ccc; }
        table.matriz th { background: #e2e8f0; font-weight: bold; text-align: center; }
        .col-concepto { text-align: left; width: 28%; }
        .col-num { text-align: right; width: 10%; }
        .col-total { text-align: right; width: 11%; font-weight: bold; }
        .saldo { font-weight: bold; border-top: 1px solid #000; }
        .neg { color: #b91c1c; }
        .warn { font-size: 8px; color: #92400e; background: #fef3c7; padding: 6px; margin-top: 8px; }
        .ok { font-size: 8px; color: #166534; background: #dcfce7; padding: 6px; margin-top: 8px; }
        .bloque-titulo { font-size: 10px; font-weight: bold; margin: 8px 0 4px 0; }
    </style>
</head>
<body>
@php
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
                if ($mime && strpos($mime, 'image/') === 0) {
                    $logoSrc = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($fullLogo));
                }
            }
        }
    }
    $columnas = $estado['columnas'] ?? [];
    $nit = $empresa->nit ?? $empresa->num_nit ?? '';
    $nrc = $empresa->nrc ?? $empresa->num_registro ?? '';
@endphp

<section id="ecp">
    <div class="header">
        @if($logoSrc)
            <img class="logo" src="{{ $logoSrc }}" alt="">
        @endif
        <h1>{{ $empresa->nombre }}</h1>
        @if($nit || $nrc)
            <p class="sub">NIT: {{ $nit ?: '—' }}@if($nrc) &nbsp;|&nbsp; NRC: {{ $nrc }}@endif</p>
        @endif
        <h2>ESTADO DE CAMBIOS EN EL PATRIMONIO NETO</h2>
        <p class="sub">{{ $estado['periodo_titulo'] ?? '' }}</p>
        <p class="sub">(Expresado en Dólares de los Estados Unidos de América)</p>
    </div>

    <p class="nota">
        Utilidad neta del ejercicio: estado de resultados NIIF del mismo rango.
        Constitución de reserva legal: 7% hasta el 20% del capital (Art. 123 Código de Comercio SV).
        El total de cierre debe coincidir con el patrimonio del balance general.
    </p>

    @foreach(($estado['bloques'] ?? []) as $bloque)
        @if(count($estado['bloques'] ?? []) > 1)
            <div class="bloque-titulo">{{ $bloque['titulo'] ?? '' }}</div>
        @endif
        <table class="matriz">
            <tr>
                <th class="col-concepto">Concepto / Movimiento</th>
                @foreach($columnas as $col)
                    <th class="col-num">{{ $col['etiqueta'] }}</th>
                @endforeach
                <th class="col-total">TOTAL</th>
            </tr>
            @foreach(($bloque['filas'] ?? []) as $fila)
                @php
                    $cls = [];
                    if (!empty($fila['es_saldo'])) { $cls[] = 'saldo'; }
                    if (!empty($fila['es_negativo'])) { $cls[] = 'neg'; }
                @endphp
                <tr class="{{ implode(' ', $cls) }}">
                    <td class="col-concepto">{{ $fila['etiqueta'] ?? '' }}</td>
                    @foreach($columnas as $col)
                        @php $v = (float) ($fila['valores'][$col['clave']] ?? 0); @endphp
                        <td class="col-num @if($v < -0.0005) neg @endif">{{ $fmtN($v) }}</td>
                    @endforeach
                    <td class="col-total @if(($fila['total'] ?? 0) < -0.0005) neg @endif">{{ $fmtN($fila['total'] ?? 0) }}</td>
                </tr>
            @endforeach
        </table>
    @endforeach

    @if(!empty($estado['validaciones']['cuadra_con_balance']))
        <div class="ok">Cuadre verificado: el patrimonio de cierre coincide con el balance general NIIF.</div>
    @else
        <div class="warn">
            @foreach(($estado['validaciones']['alertas'] ?? []) as $alerta)
                <div>⚠ {{ $alerta }}</div>
            @endforeach
        </div>
    @endif
</section>
</body>
</html>
