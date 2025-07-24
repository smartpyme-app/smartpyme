<!DOCTYPE html>
<html>
<head>
    <title>Estado de Resultados</title>
</head>
<body>
<table>
    <thead>
    <tr>
        <th colspan="3" style="text-align: center; font-size: 18px;"><strong>{{ $empresa->nombre }}</strong></th>
    </tr>
    <tr>
        <th colspan="3" style="text-align: center; font-size: 16px;"><strong>ESTADO DE RESULTADOS</strong></th>
    </tr>
    <tr>
        <th colspan="3" style="text-align: center; font-size: 14px;"><strong>Del 1 al {{ date('t', mktime(0, 0, 0, $month, 1, $year)) }} de {{ $month_name }} de {{ $year }}</strong></th>
    </tr>
    <tr>
        <th colspan="3" style="text-align: center; font-size: 12px;"><strong>(Expresado en US Dólares)</strong></th>
    </tr>
    <tr></tr>
    <tr>
        <th style="text-align: center; font-weight: bold; background-color: #f0f0f0;">Código</th>
        <th style="text-align: center; font-weight: bold; background-color: #f0f0f0;">Descripción</th>
        <th style="text-align: center; font-weight: bold; background-color: #f0f0f0;">Importe</th>
    </tr>
    </thead>
    <tbody>
    <!-- INGRESOS -->
    <tr style="background-color: #e8f5e8;">
        <td colspan="3" style="text-align: center; font-weight: bold; font-size: 14px;">INGRESOS</td>
    </tr>
    @foreach($estado_resultados['ingresos'] as $ingreso)
        <tr>
            <td>{{ $ingreso['codigo'] }}</td>
            <td>{{ $ingreso['nombre'] }}</td>
            <td style="text-align: right;">{{ number_format(abs($ingreso['saldo_final']), 2) }}</td>
        </tr>
    @endforeach
    @if(count($estado_resultados['ingresos']) > 0)
        <tr style="background-color: #f0f0f0; font-weight: bold;">
            <td colspan="2" style="text-align: center; font-weight: bold;">TOTAL INGRESOS</td>
            <td style="text-align: right; font-weight: bold;">{{ number_format($estado_resultados['totales']['ingresos'], 2) }}</td>
        </tr>
    @endif

    <tr></tr>

    <!-- COSTOS Y GASTOS -->
    <tr style="background-color: #ffe8e8;">
        <td colspan="3" style="text-align: center; font-weight: bold; font-size: 14px;">COSTOS Y GASTOS</td>
    </tr>
    @foreach($estado_resultados['costos_gastos'] as $costo_gasto)
        <tr>
            <td>{{ $costo_gasto['codigo'] }}</td>
            <td>{{ $costo_gasto['nombre'] }}</td>
            <td style="text-align: right;">{{ number_format(abs($costo_gasto['saldo_final']), 2) }}</td>
        </tr>
    @endforeach
    @if(count($estado_resultados['costos_gastos']) > 0)
        <tr style="background-color: #f0f0f0; font-weight: bold;">
            <td colspan="2" style="text-align: center; font-weight: bold;">TOTAL COSTOS Y GASTOS</td>
            <td style="text-align: right; font-weight: bold;">{{ number_format($estado_resultados['totales']['costos_gastos'], 2) }}</td>
        </tr>
    @endif

    <tr></tr>

    <!-- UTILIDAD/PÉRDIDA -->
    <tr style="{{ $estado_resultados['totales']['utilidad_perdida'] >= 0 ? 'background-color: #d4edda;' : 'background-color: #f8d7da;' }}">
        <td colspan="2" style="text-align: center; font-weight: bold; font-size: 14px;">
            {{ $estado_resultados['totales']['utilidad_perdida'] >= 0 ? 'UTILIDAD DEL PERÍODO' : 'PÉRDIDA DEL PERÍODO' }}
        </td>
        <td style="text-align: right; font-weight: bold; font-size: 14px;">
            {{ number_format(abs($estado_resultados['totales']['utilidad_perdida']), 2) }}
        </td>
    </tr>

    <!-- RESUMEN -->
    <tr></tr>
    <tr style="background-color: #f8f9fa;">
        <td colspan="3" style="text-align: center; font-weight: bold;">RESUMEN FINANCIERO</td>
    </tr>
    <tr>
        <td colspan="2" style="font-weight: bold;">Total Ingresos:</td>
        <td style="text-align: right; font-weight: bold;">{{ number_format($estado_resultados['totales']['ingresos'], 2) }}</td>
    </tr>
    <tr>
        <td colspan="2" style="font-weight: bold;">Total Costos y Gastos:</td>
        <td style="text-align: right; font-weight: bold;">{{ number_format($estado_resultados['totales']['costos_gastos'], 2) }}</td>
    </tr>
    <tr style="{{ $estado_resultados['totales']['utilidad_perdida'] >= 0 ? 'background-color: #d4edda;' : 'background-color: #f8d7da;' }}">
        <td colspan="2" style="font-weight: bold;">
            {{ $estado_resultados['totales']['utilidad_perdida'] >= 0 ? 'Utilidad Neta:' : 'Pérdida Neta:' }}
        </td>
        <td style="text-align: right; font-weight: bold;">
            {{ number_format(abs($estado_resultados['totales']['utilidad_perdida']), 2) }}
        </td>
    </tr>
    </tbody>
</table>
</body>
</html>
