<!DOCTYPE html>
<html>
<head>
    <title>Balance de comprobación</title>
</head>
<body>
<table>
    <thead>
    <tr>
        <th colspan="8" style="text-align: center; font-size: 16px;"><strong>Balance de Comprobación</strong></th>
    </tr>
    <tr>
        <th colspan="8" style="text-align: center; font-size: 16px;"><strong>Empresa: {{ $empresa->nombre }}</strong></th>
    </tr>
    <tr>
        <th colspan="8" style="text-align: center; font-size: 16px;"><strong>Período: {{ $month_name }} - {{ $year }}</strong></th>
    </tr>
    <tr>
        <th colspan="8" style="text-align: center; font-size: 16px;"><strong>Todos los Centros de Costos</strong></th>
    </tr>
    <tr>
        <th colspan="8" style="text-align: center; font-size: 16px;"><strong>Valores expresados en US dólares</strong></th>
    </tr>
    <tr>
        <th colspan="8" style="text-align: center; font-size: 16px;"><strong>Activos y Gastos</strong></th>
    </tr>
    <tr></tr>
    <tr>
        <th style="text-align: center; font-weight: bold;">Código</th>
        <th style="text-align: center; font-weight: bold;">Nombre</th>
        <th style="text-align: center; font-weight: bold;">Naturaleza</th>
        <th style="text-align: center; font-weight: bold;">Saldo Inicial</th>
        <th style="text-align: center; font-weight: bold;">Cargo</th>
        <th style="text-align: center; font-weight: bold;">Abono</th>
        <th style="text-align: center; font-weight: bold;">Operaciones del mes</th>
        <th style="text-align: center; font-weight: bold;">Saldo Final</th>
    </tr>
    </thead>
    <tbody>
    @foreach($balanceComprobacion as $cuenta)
        <tr style="{{ $cuenta['es_cuenta_padre'] ? 'background-color: #f8f9fa; font-weight: bold;' : '' }}">
            <td class="codigo">{{ $cuenta['codigo'] }}{{ $cuenta['es_cuenta_padre'] ? ' (P)' : '' }}</td>
            <td class="nombre">{{ $cuenta['nombre'] }}</td>
            <td class="naturaleza">{{ $cuenta['naturaleza'] ?? 'N/A' }}</td>
            <td class="sal_inic">{{ number_format($cuenta['saldo_inicial'] ?? 0, 2) }}</td>
            <td class="cargo">{{ number_format($cuenta['debe'] ?? 0, 2) }}</td>
            <td class="abono">{{ number_format($cuenta['haber'] ?? 0, 2) }}</td>
            <td class="operaciones_mes">{{ number_format($cuenta['operaciones_mes'] ?? 0, 2) }}</td>
            <td class="sal_fin">{{ number_format($cuenta['saldo_final'] ?? 0, 2) }}</td>
        </tr>
    @endforeach

    @if(isset($totales))
        <tr></tr>
        <tr style="background-color: #fff3cd;">
            <td colspan="8" style="text-align: center; font-weight: bold;">NOTA: Los totales solo incluyen cuentas padre (nivel = 0)</td>
        </tr>
        <tr style="background-color: #fff3cd;">
            <td colspan="8" style="text-align: center; font-style: italic;">Las cuentas padre (P) consolidan los valores de sus subcuentas</td>
        </tr>
        <tr></tr>
        <tr style="background-color: #f0f0f0; font-weight: bold;">
            <td colspan="3" style="text-align: center; font-weight: bold;">TOTALES</td>
            <td style="text-align: right; font-weight: bold;">{{ number_format($totales['saldo_inicial'] ?? 0, 2) }}</td>
            <td style="text-align: right; font-weight: bold;">{{ number_format($totales['debe'] ?? 0, 2) }}</td>
            <td style="text-align: right; font-weight: bold;">{{ number_format($totales['haber'] ?? 0, 2) }}</td>
            <td style="text-align: right; font-weight: bold;">{{ number_format($totales['diferencia'] ?? 0, 2) }}</td>
            <td style="text-align: right; font-weight: bold;">{{ number_format($totales['saldo_final'] ?? 0, 2) }}</td>
        </tr>
        <tr></tr>
        <tr style="background-color: #e0e0e0; font-weight: bold;">
            <td colspan="6" style="text-align: center; font-weight: bold;">DIFERENCIA (Debe - Haber)</td>
            <td style="text-align: right; font-weight: bold;">{{ number_format($totales['diferencia'] ?? 0, 2) }}</td>
            <td style="text-align: right; font-weight: bold;"></td>
        </tr>
    @endif
    </tbody>
</table>
</body>
</html>
