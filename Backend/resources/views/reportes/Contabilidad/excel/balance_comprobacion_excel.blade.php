<!DOCTYPE html>
<html>
<head>
    <title>Balance de comprobación</title>
</head>
<body>
<table>
    <thead>
    <tr>
        <th colspan="6" style="text-align: center; font-size: 16px;"><strong>Balance de Comprobación</strong></th>
    </tr>
    <tr>
        <th colspan="6" style="text-align: center; font-size: 16px;"><strong>Empresa: {{ $empresa->nombre }}</strong></th>
    </tr>
    <tr>
        <th colspan="6" style="text-align: center; font-size: 16px;"><strong>Período: {{ $month_name }} - {{ $year }}</strong></th>
    </tr>
    <tr>
        <th colspan="6" style="text-align: center; font-size: 16px;"><strong>Todos los Centros de Costos</strong></th>
    </tr>
    <tr>
        <th colspan="6" style="text-align: center; font-size: 16px;"><strong>Valores expresados en US dólares</strong></th>
    </tr>
    <tr>
        <th colspan="6" style="text-align: center; font-size: 16px;"><strong>Activos y Gastos</strong></th>
    </tr>
    <tr></tr>
    <tr>
        <th style="text-align: center; font-weight: bold;">Código</th>
        <th style="text-align: center; font-weight: bold;">Nombre</th>
        <th style="text-align: center; font-weight: bold;">Saldo Inicial</th>
        <th style="text-align: center; font-weight: bold;">Cargo</th>
        <th style="text-align: center; font-weight: bold;">Abono</th>
        <th style="text-align: center; font-weight: bold;">Saldo Final</th>
    </tr>
    </thead>
    <tbody>
    @foreach($balanceComprobacion as $cuenta)
        <tr>
            <td class="codigo">{{ $cuenta['codigo'] }}</td>
            <td class="nombre">{{ $cuenta['nombre'] }}</td>
            <td class="sal_inic">{{ number_format($cuenta['saldo_inicial'], 2) }}</td>
            <td class="cargo">{{ number_format($cuenta['debe'], 2) }}</td>
            <td class="abono">{{ number_format($cuenta['debe'], 2) }}</td>
            <td class="sal_fin">{{ number_format($cuenta['saldo_final'], 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
