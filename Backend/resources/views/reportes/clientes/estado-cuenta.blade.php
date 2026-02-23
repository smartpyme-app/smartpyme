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
            margin: 50px;
            font-size: 10px;
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
        
        .col-documento { width: 8%; }
        .col-fecha-doc { width: 8%; }
        .col-valor-doc { width: 8%; }
        .col-plazo { width: 6%; }
        .col-vence { width: 8%; }
        .col-saldo { width: 9%; }
        .col-dias-mora { width: 6%; }
        .col-sin-vencer { width: 8%; }
        .col-30 { width: 8%; }
        .col-60 { width: 8%; }
        .col-90 { width: 8%; }
        .col-120 { width: 8%; }
        .col-mas120 { width: 8%; }
    </style>
</head>
<body>
    @php
        $fechaActual = \Carbon\Carbon::now();
        $fechaActualStr = $fechaActual->format('d/m/Y');
        $horaActualStr = $fechaActual->format('H:i:s');
        
        // Obtener la empresa y su moneda
        $empresa = $cliente->empresa()->first();
        $simboloMoneda = $empresa && $empresa->currency ? $empresa->currency->currency_symbol : '$';
        
        // Calcular antigüedad para cada venta
        $ventasConAntiguedad = [];
        $totales = [
            'saldo' => 0,
            'sin_vencer' => 0,
            'dias_30' => 0,
            'dias_60' => 0,
            'dias_90' => 0,
            'dias_120' => 0,
            'mas_120' => 0
        ];
        
        foreach($cliente->ventas as $venta) {
            $fechaDoc = \Carbon\Carbon::parse($venta->fecha);
            $fechaVence = $venta->fecha_pago ? \Carbon\Carbon::parse($venta->fecha_pago) : $fechaDoc->copy()->addDays(30);
            
            // Calcular plazo en días
            $plazoDias = $fechaDoc->diffInDays($fechaVence);
            
            // Calcular días de mora (positivo si está vencido)
            if ($fechaActual->greaterThan($fechaVence)) {
                $diasMora = $fechaActual->diffInDays($fechaVence);
            } else {
                $diasMora = 0;
            }
            
            // Clasificar según antigüedad
            $sinVencer = 0;
            $dias30 = 0;
            $dias60 = 0;
            $dias90 = 0;
            $dias120 = 0;
            $mas120 = 0;
            
            if ($diasMora == 0) {
                $sinVencer = $venta->total;
            } elseif ($diasMora <= 30) {
                $dias30 = $venta->total;
            } elseif ($diasMora <= 60) {
                $dias60 = $venta->total;
            } elseif ($diasMora <= 90) {
                $dias90 = $venta->total;
            } elseif ($diasMora <= 120) {
                $dias120 = $venta->total;
            } else {
                $mas120 = $venta->total;
            }
            
            $ventasConAntiguedad[] = [
                'venta' => $venta,
                'fecha_doc' => $fechaDoc,
                'fecha_vence' => $fechaVence,
                'plazo_dias' => $plazoDias,
                'dias_mora' => $diasMora,
                'sin_vencer' => $sinVencer,
                'dias_30' => $dias30,
                'dias_60' => $dias60,
                'dias_90' => $dias90,
                'dias_120' => $dias120,
                'mas_120' => $mas120
            ];
            
            // Acumular totales
            $totales['saldo'] += $venta->total;
            $totales['sin_vencer'] += $sinVencer;
            $totales['dias_30'] += $dias30;
            $totales['dias_60'] += $dias60;
            $totales['dias_90'] += $dias90;
            $totales['dias_120'] += $dias120;
            $totales['mas_120'] += $mas120;
        }
    @endphp
    
    <!-- Encabezado simplificado -->
    <div class="header-simple">
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
            </tr>
        </thead>
        <tbody>
            @foreach($ventasConAntiguedad as $item)
                <tr>
                    <td>{{ $item['venta']->nombre_documento }} #{{ $item['venta']->correlativo }}</td>
                    <td>{{ $item['fecha_doc']->format('d/m/Y') }}</td>
                    <td class="text-right">{{ $simboloMoneda }}{{ number_format($item['venta']->total, 2, '.', ',') }}</td>
                    <td>{{ $item['plazo_dias'] }}</td>
                    <td>{{ $item['fecha_vence']->format('d/m/Y') }}</td>
                    <td class="text-right">{{ $simboloMoneda }}{{ number_format($item['venta']->total, 2, '.', ',') }}</td>
                    <td>{{ $item['dias_mora'] }}</td>
                    <td class="text-right">{{ $item['sin_vencer'] > 0 ? $simboloMoneda . number_format($item['sin_vencer'], 2, '.', ',') : $simboloMoneda . '0.00' }}</td>
                    <td class="text-right">{{ $item['dias_30'] > 0 ? $simboloMoneda . number_format($item['dias_30'], 2, '.', ',') : $simboloMoneda . '0.00' }}</td>
                    <td class="text-right">{{ $item['dias_60'] > 0 ? $simboloMoneda . number_format($item['dias_60'], 2, '.', ',') : $simboloMoneda . '0.00' }}</td>
                    <td class="text-right">{{ $item['dias_90'] > 0 ? $simboloMoneda . number_format($item['dias_90'], 2, '.', ',') : $simboloMoneda . '0.00' }}</td>
                    <td class="text-right">{{ $item['dias_120'] > 0 ? $simboloMoneda . number_format($item['dias_120'], 2, '.', ',') : $simboloMoneda . '0.00' }}</td>
                    <td class="text-right">{{ $item['mas_120'] > 0 ? $simboloMoneda . number_format($item['mas_120'], 2, '.', ',') : $simboloMoneda . '0.00' }}</td>
                </tr>
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
            </tr>
        </tbody>
    </table>
</body>
</html>
