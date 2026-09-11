<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Antigüedad de Saldos por Pagar - {{ $cliente->tipo == 'Empresa' ? $cliente->nombre_empresa : $cliente->nombre_completo }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: sans-serif;
            margin: 36px;
            font-size: 9px;
        }

        .logo {
            display: block;
            max-height: 56px;
            max-width: 160px;
            margin: 0 auto 8px auto;
        }
        
        h1,h2,h3,h4,h5,h6{
            color: #000000 !important;
        }
        
        @page {
            margin: 50px 50px 60px 50px;
        }
        
        .header-simple {
            margin-bottom: 20px;
        }
        
        .title {
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 10px;
            text-transform: uppercase;
            color: #000000 !important;
        }
        
        .cliente-nombre {
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 5px;
            color: #000000 !important;
        }
        
        .fecha-header {
            text-align: center;
            margin-bottom: 15px;
            font-size: 10px;
            color: #000000 !important;
        }
        
        .antiguedad-fecha {
            text-align: center;
            margin-bottom: 15px;
            font-size: 10px;
            color: #000000 !important;
        }
        
        .antiguedad-header {
            display: table;
            width: 100%;
            margin-bottom: 5px;
        }
        
        .antiguedad-header-cell {
            display: table-cell;
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            padding: 3px;
            border: 0px;
            border: 1px solid #000000 !important;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            font-size: 10px;
        }
        
        .table th,
        .table td {
            border: 0px;
            border: 1px solid #606060 !important;
            padding: 4px 3px;
            text-align: center;
        }
        
        .table th {
            font-weight: bold;
            font-size: 10px;
        }
        
        .table td {
            font-size: 10px;
        }
        
        .border {
            border: 1px solid #000000 !important;
        }
        
        .text-left {
            text-align: left !important;
        }
        
        .text-right {
            text-align: right !important;
        }
        
        .text-center {
            text-align: center !important;
        }
        
        .total-row {
            font-weight: bold;
        }
        
        .total-general-row {
            font-weight: bold;
            border-top: 2px solid #000000 !important;
        }

        .row-abono td {
            font-size: 9px;
            font-style: italic;
            color: #333333 !important;
            background-color: #fafafa;
        }

        .row-abono .col-documento {
            text-align: left !important;
            padding-left: 10px !important;
        }
        
        .col-documento { width: 8%; }
        .col-fecha-doc { width: 7%; }
        .col-valor-doc { width: 7%; }
        .col-plazo { width: 5%; }
        .col-vence { width: 7%; }
        .col-saldo { width: 8%; }
        .col-dias-mora { width: 5%; }
        .col-sin-vencer { width: 7%; }
        .col-30 { width: 7%; }
        .col-60 { width: 7%; }
        .col-90 { width: 7%; }
        .col-120 { width: 7%; }
        .col-mas120 { width: 8%; }
        .col-mas365 { width: 7%; }
    </style>
</head>
<body>
    @php
        $fechaActual = \Carbon\Carbon::now();
        $fechaActualStr = $fechaActual->format('d/m/Y');

        $empresa = $cliente->empresa;
        $simboloMoneda = $empresa && $empresa->currency ? $empresa->currency->currency_symbol : '$';

        $logoSrc = null;
        if (!empty($empresa->logo)) {
            $logoRel = ltrim(str_replace('\\', '/', (string) $empresa->logo), '/');
            if ($logoRel !== '' && strpos($logoRel, '..') === false) {
                $fullLogo = public_path('img'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $logoRel));
                if (is_file($fullLogo)) {
                    $mime = null;
                    if (function_exists('finfo_open')) {
                        $fi = finfo_open(FILEINFO_MIME_TYPE);
                        if ($fi) {
                            $mime = finfo_file($fi, $fullLogo) ?: null;
                            finfo_close($fi);
                        }
                    }
                    if ($mime && strpos($mime, 'image/') === 0) {
                        $logoSrc = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($fullLogo));
                    }
                }
            }
        }

        $ventasConAntiguedad = [];
        $totales = [
            'saldo' => 0,
            'sin_vencer' => 0,
            'dias_30' => 0,
            'dias_60' => 0,
            'dias_90' => 0,
            'dias_120' => 0,
            'mas_120' => 0,
            'mas_365' => 0,
        ];

        foreach($cliente->ventas as $venta) {
            $fechaDoc = \Carbon\Carbon::parse($venta->fecha);
            $fechaVence = $venta->fecha_pago ? \Carbon\Carbon::parse($venta->fecha_pago) : $fechaDoc->copy()->addDays(30);
            $plazoDias = \App\Helpers\EstadoCuentaAntiguedadHelper::plazoDias($fechaDoc, $fechaVence);
            $diasMora = \App\Helpers\EstadoCuentaAntiguedadHelper::diasMora($fechaVence, $fechaActual);
            $saldoPendiente = max(0, round((float) $venta->saldo, 2));

            $montos = [
                'sin_vencer' => 0,
                'dias_30' => 0,
                'dias_60' => 0,
                'dias_90' => 0,
                'dias_120' => 0,
                'mas_120' => 0,
                'mas_365' => 0,
            ];
            if ($saldoPendiente > 0) {
                $montos[\App\Helpers\EstadoCuentaAntiguedadHelper::bucket($diasMora)] = $saldoPendiente;
            }

            $ventasConAntiguedad[] = [
                'venta' => $venta,
                'fecha_doc' => $fechaDoc,
                'fecha_vence' => $fechaVence,
                'plazo_dias' => $plazoDias,
                'dias_mora' => $diasMora,
            ] + $montos;

            $totales['saldo'] += $saldoPendiente;
            foreach ($montos as $key => $monto) {
                $totales[$key] += $monto;
            }
        }
    @endphp
    
    <!-- Encabezado simplificado -->
    <div class="header-simple">
        @if($logoSrc)
            <img class="logo" src="{{ $logoSrc }}" alt="">
        @endif
        <div class="title">ANTIGÜEDAD DE SALDOS POR PAGAR</div>
        <div class="cliente-nombre">
            {{ $cliente->tipo == 'Empresa' ? $cliente->nombre_empresa : $cliente->nombre_completo }}
            @if($cliente->tipo == 'Empresa' && $cliente->ncr)
                ({{ $cliente->ncr }})
            @elseif($cliente->dui)
                ({{ $cliente->dui }})
            @endif
        </div>
        <div class="fecha-header">
            Fecha: {{ $fechaActualStr }}
        </div>
    </div>

    <!-- Tabla principal -->
    <table class="table">
        <thead>
            <tr>
                <th class="col-documento">Documento</th>
                <th class="col-fecha-doc">Fecha Doc.</th>
                <th class="col-valor-doc">Valor Doc.</th>
                <th class="col-plazo">Plazo (días)</th>
                <th class="col-vence">Vence</th>
                <th class="col-saldo">Saldo</th>
                <th class="col-dias-mora">Días Mora</th>
                <th class="col-sin-vencer">Sin Vencer</th>
                <th class="col-30">30 días</th>
                <th class="col-60">60 días</th>
                <th class="col-90">90 días</th>
                <th class="col-120">120 días</th>
                <th class="col-mas120">Más de 120</th>
                <th class="col-mas365">+365 días</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ventasConAntiguedad as $item)
                @php $v = $item['venta']; $saldoDoc = max(0, round((float) $v->saldo, 2)); @endphp
                <tr>
                    <td class="col-documento text-left">{{ $v->nombre_documento }} #{{ $v->correlativo }}</td>
                    <td>{{ $item['fecha_doc']->format('d/m/Y') }}</td>
                    <td class="text-right">{{ $simboloMoneda }}{{ number_format($v->total, 2, '.', ',') }}</td>
                    <td>{{ $item['plazo_dias'] }}</td>
                    <td>{{ $item['fecha_vence']->format('d/m/Y') }}</td>
                    <td class="text-right">{{ $simboloMoneda }}{{ number_format($saldoDoc, 2, '.', ',') }}</td>
                    <td>{{ $item['dias_mora'] }}</td>
                    <td class="text-right">{{ $item['sin_vencer'] > 0 ? $simboloMoneda . number_format($item['sin_vencer'], 2, '.', ',') : $simboloMoneda . '0.00' }}</td>
                    <td class="text-right">{{ $item['dias_30'] > 0 ? $simboloMoneda . number_format($item['dias_30'], 2, '.', ',') : $simboloMoneda . '0.00' }}</td>
                    <td class="text-right">{{ $item['dias_60'] > 0 ? $simboloMoneda . number_format($item['dias_60'], 2, '.', ',') : $simboloMoneda . '0.00' }}</td>
                    <td class="text-right">{{ $item['dias_90'] > 0 ? $simboloMoneda . number_format($item['dias_90'], 2, '.', ',') : $simboloMoneda . '0.00' }}</td>
                    <td class="text-right">{{ $item['dias_120'] > 0 ? $simboloMoneda . number_format($item['dias_120'], 2, '.', ',') : $simboloMoneda . '0.00' }}</td>
                    <td class="text-right">{{ $item['mas_120'] > 0 ? $simboloMoneda . number_format($item['mas_120'], 2, '.', ',') : $simboloMoneda . '0.00' }}</td>
                    <td class="text-right">{{ $item['mas_365'] > 0 ? $simboloMoneda . number_format($item['mas_365'], 2, '.', ',') : $simboloMoneda . '0.00' }}</td>
                </tr>
                @foreach($v->abonos as $abono)
                    @php
                        $nomDocAbono = optional($abono->documento)->nombre ?? 'Abono';
                        $corrAbono = $abono->correlativo ?? $abono->id;
                        $fechaAbono = \Carbon\Carbon::parse($abono->fecha);
                    @endphp
                    <tr class="row-abono">
                        <td class="col-documento">- {{ $nomDocAbono }} #{{ $corrAbono }}@if($abono->concepto)<br><span style="font-style:normal;font-size:8px;">{{ \Illuminate\Support\Str::limit($abono->concepto, 48) }}</span>@endif</td>
                        <td>{{ $fechaAbono->format('d/m/Y') }}</td>
                        <td class="text-right">{{ $simboloMoneda }}{{ number_format((float) $abono->total, 2, '.', ',') }}</td>
                        <td>—</td>
                        <td>—</td>
                        <td>—</td>
                        <td>—</td>
                        <td>—</td>
                        <td>—</td>
                        <td>—</td>
                        <td>—</td>
                        <td>—</td>
                        <td>—</td>
                        <td>—</td>
                    </tr>
                @endforeach
            @endforeach
            
            <!-- Total por cliente -->
            @if(count($ventasConAntiguedad) > 0)
                <tr class="total-row">
                    <td colspan="5" class="text-right"><strong>TOTAL</strong></td>
                    <td class="text-right"><strong>{{ $simboloMoneda }}{{ number_format($totales['saldo'], 2, '.', ',') }}</strong></td>
                    <td></td>
                    <td class="text-right"><strong>{{ $simboloMoneda }}{{ number_format($totales['sin_vencer'], 2, '.', ',') }}</strong></td>
                    <td class="text-right"><strong>{{ $simboloMoneda }}{{ number_format($totales['dias_30'], 2, '.', ',') }}</strong></td>
                    <td class="text-right"><strong>{{ $simboloMoneda }}{{ number_format($totales['dias_60'], 2, '.', ',') }}</strong></td>
                    <td class="text-right"><strong>{{ $simboloMoneda }}{{ number_format($totales['dias_90'], 2, '.', ',') }}</strong></td>
                    <td class="text-right"><strong>{{ $simboloMoneda }}{{ number_format($totales['dias_120'], 2, '.', ',') }}</strong></td>
                    <td class="text-right"><strong>{{ $simboloMoneda }}{{ number_format($totales['mas_120'], 2, '.', ',') }}</strong></td>
                    <td class="text-right"><strong>{{ $simboloMoneda }}{{ number_format($totales['mas_365'], 2, '.', ',') }}</strong></td>
                </tr>
            @endif
            
            <!-- Total General -->
            <tr class="total-general-row">
                <td colspan="5" class="text-right"><strong>TOTAL GENERAL</strong></td>
                <td class="text-right"><strong>{{ $simboloMoneda }}{{ number_format($totales['saldo'], 2, '.', ',') }}</strong></td>
                <td></td>
                <td class="text-right"><strong>{{ $simboloMoneda }}{{ number_format($totales['sin_vencer'], 2, '.', ',') }}</strong></td>
                <td class="text-right"><strong>{{ $simboloMoneda }}{{ number_format($totales['dias_30'], 2, '.', ',') }}</strong></td>
                <td class="text-right"><strong>{{ $simboloMoneda }}{{ number_format($totales['dias_60'], 2, '.', ',') }}</strong></td>
                <td class="text-right"><strong>{{ $simboloMoneda }}{{ number_format($totales['dias_90'], 2, '.', ',') }}</strong></td>
                <td class="text-right"><strong>{{ $simboloMoneda }}{{ number_format($totales['dias_120'], 2, '.', ',') }}</strong></td>
                <td class="text-right"><strong>{{ $simboloMoneda }}{{ number_format($totales['mas_120'], 2, '.', ',') }}</strong></td>
                <td class="text-right"><strong>{{ $simboloMoneda }}{{ number_format($totales['mas_365'], 2, '.', ',') }}</strong></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
