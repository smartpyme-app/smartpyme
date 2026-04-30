<!DOCTYPE html>
<html>
<head>
    <title>Balance General</title>
</head>
<body>
<table>
    <thead>
    <tr>
        <th colspan="6" style="text-align: center; font-size: 18px;"><strong>{{ $empresa->nombre }}</strong></th>
    </tr>
    <tr>
        <th colspan="6" style="text-align: center; font-size: 16px;"><strong>BALANCE GENERAL</strong></th>
    </tr>
    <tr>
        <th colspan="6" style="text-align: center; font-size: 14px;"><strong>Al {{ $month_name }} de {{ $year }}</strong></th>
    </tr>
    <tr>
        <th colspan="6" style="text-align: center; font-size: 12px;"><strong>(Expresado en US Dólares)</strong></th>
    </tr>
    <tr></tr>
    <tr>
        <th style="text-align: center; font-weight: bold; background-color: #f0f0f0;">Código</th>
        <th style="text-align: center; font-weight: bold; background-color: #f0f0f0;">ACTIVOS</th>
        <th style="text-align: center; font-weight: bold; background-color: #f0f0f0;">Importe</th>
        <th style="text-align: center; font-weight: bold; background-color: #f0f0f0;">Código</th>
        <th style="text-align: center; font-weight: bold; background-color: #f0f0f0;">PASIVOS Y PATRIMONIO</th>
        <th style="text-align: center; font-weight: bold; background-color: #f0f0f0;">Importe</th>
    </tr>
    </thead>
    <tbody>
    @php
        $maxRows = max(
            count($balance_general['activos']),
            count($balance_general['pasivos']) + count($balance_general['patrimonio']) + 2
        );
        $pasivos_patrimonio = array_merge($balance_general['pasivos'], $balance_general['patrimonio']);
    @endphp

    @for($i = 0; $i < $maxRows; $i++)
        <tr>
            <!-- COLUMNA ACTIVOS -->
            @if($i < count($balance_general['activos']))
                @php $activo = $balance_general['activos'][$i]; @endphp
                <td>{{ $activo['codigo'] }}</td>
                <td>{{ $activo['nombre'] }}</td>
                <td style="text-align: right;">{{ number_format(abs($activo['saldo_final']), 2) }}</td>
            @else
                <td></td>
                <td></td>
                <td></td>
            @endif

            <!-- COLUMNA PASIVOS Y PATRIMONIO -->
            @if($i < count($balance_general['pasivos']))
                @php $pasivo = $balance_general['pasivos'][$i]; @endphp
                <td>{{ $pasivo['codigo'] }}</td>
                <td>{{ $pasivo['nombre'] }}</td>
                <td style="text-align: right;">{{ number_format(abs($pasivo['saldo_final']), 2) }}</td>
            @elseif($i == count($balance_general['pasivos']) && count($balance_general['pasivos']) > 0)
                <!-- Subtotal Pasivos -->
                <td></td>
                <td style="font-weight: bold; border-top: 1px solid black;">TOTAL PASIVOS</td>
                <td style="text-align: right; font-weight: bold; border-top: 1px solid black;">{{ number_format(abs($balance_general['totales']['pasivos']), 2) }}</td>
            @elseif($i == count($balance_general['pasivos']) + 1)
                <!-- Espacio -->
                <td></td>
                <td></td>
                <td></td>
            @elseif($i >= count($balance_general['pasivos']) + 2 && $i < count($balance_general['pasivos']) + 2 + count($balance_general['patrimonio']))
                @php $patrimonioIndex = $i - count($balance_general['pasivos']) - 2; @endphp
                @php $patrimonio = $balance_general['patrimonio'][$patrimonioIndex]; @endphp
                <td>{{ $patrimonio['codigo'] }}</td>
                <td>{{ $patrimonio['nombre'] }}</td>
                <td style="text-align: right;">{{ number_format(abs($patrimonio['saldo_final']), 2) }}</td>
            @else
                <td></td>
                <td></td>
                <td></td>
            @endif
        </tr>
    @endfor

    <!-- TOTALES -->
    <tr style="border-top: 2px solid black;">
        <td></td>
        <td style="font-weight: bold;">TOTAL ACTIVOS</td>
        <td style="text-align: right; font-weight: bold; border-bottom: 2px solid black;">{{ number_format(abs($balance_general['totales']['activos']), 2) }}</td>

        <td></td>
        @if(count($balance_general['patrimonio']) > 0)
            <td style="font-weight: bold; border-top: 1px solid black;">TOTAL PATRIMONIO</td>
            <td style="text-align: right; font-weight: bold; border-top: 1px solid black;">{{ number_format(abs($balance_general['totales']['patrimonio']), 2) }}</td>
        @else
            <td></td>
            <td></td>
        @endif
    </tr>

    <tr>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        <td style="font-weight: bold; border-top: 2px solid black;">TOTAL PASIVOS + PATRIMONIO</td>
        <td style="text-align: right; font-weight: bold; border-top: 2px solid black; border-bottom: 2px solid black;">{{ number_format(abs($balance_general['totales']['pasivos'] + $balance_general['totales']['patrimonio']), 2) }}</td>
    </tr>

    <!-- VERIFICACIÓN DE ECUACIÓN CONTABLE -->
    <tr></tr>
    <tr style="{{ $balance_general['ecuacion_cuadra'] ? 'background-color: #d4edda;' : 'background-color: #f8d7da;' }}">
        <td colspan="6" style="text-align: center; font-weight: bold;">
            @if($balance_general['ecuacion_cuadra'])
                ✓ ECUACIÓN CONTABLE VERIFICADA: ACTIVOS = PASIVOS + PATRIMONIO
            @else
                ⚠ DIFERENCIA EN ECUACIÓN CONTABLE: {{ number_format(abs($balance_general['totales']['activos'] - ($balance_general['totales']['pasivos'] + $balance_general['totales']['patrimonio'])), 2) }}
            @endif
        </td>
    </tr>

    <!-- RESUMEN -->
    <tr></tr>
    <tr style="background-color: #f8f9fa;">
        <td colspan="6" style="text-align: center; font-weight: bold;">RESUMEN FINANCIERO</td>
    </tr>
    <tr>
        <td colspan="2" style="font-weight: bold;">Total Activos:</td>
        <td style="text-align: right; font-weight: bold;">{{ number_format(abs($balance_general['totales']['activos']), 2) }}</td>
        <td colspan="2" style="font-weight: bold;">Total Pasivos:</td>
        <td style="text-align: right; font-weight: bold;">{{ number_format(abs($balance_general['totales']['pasivos']), 2) }}</td>
    </tr>
    <tr>
        <td colspan="2"></td>
        <td></td>
        <td colspan="2" style="font-weight: bold;">Total Patrimonio:</td>
        <td style="text-align: right; font-weight: bold;">{{ number_format(abs($balance_general['totales']['patrimonio']), 2) }}</td>
    </tr>
    </tbody>
</table>
</body>
</html>
