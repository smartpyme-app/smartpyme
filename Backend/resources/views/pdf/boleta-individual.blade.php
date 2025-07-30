<!DOCTYPE html>
<html>
<head>
    <title>Boleta de Pago</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .info-empleado {
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .totales {
            margin-top: 20px;
            text-align: right;
        }
        .firma {
            margin-top: 50px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ $detalle->planilla->empresa->nombre }}</h2>
        <h3>Boleta de Pago</h3>
        <p>Período: {{ date('d/m/Y', strtotime($periodo['inicio'])) }} - {{ date('d/m/Y', strtotime($periodo['fin'])) }}</p>
    </div>

    <div class="info-empleado">
        <p><strong>Empleado:</strong> {{ $detalle->empleado->nombres }} {{ $detalle->empleado->apellidos }}</p>
        <p><strong>Código:</strong> {{ $detalle->empleado->codigo }}</p>
        <p><strong>Cargo:</strong> {{ $detalle->empleado->cargo->nombre }}</p>
        <p><strong>Departamento:</strong> {{ $detalle->empleado->departamento->nombre }}</p>
    </div>

    <table>
        <tr>
            <th colspan="2">Ingresos</th>
            <th colspan="2">Deducciones</th>
        </tr>
        <tr>
            <td>Salario Base</td>
            <td class="monto">${{ number_format($detalle->salario_base, 2) }}</td>
            <td>ISSS</td>
            <td class="monto">${{ number_format($detalle->isss_empleado, 2) }}</td>
        </tr>
        <tr>
            <td>Horas Extra</td>
            <td class="monto">${{ number_format($detalle->monto_horas_extra, 2) }}</td>
            <td>AFP</td>
            <td class="monto">${{ number_format($detalle->afp_empleado, 2) }}</td>
        </tr>
        <tr>
            <td>Comisiones</td>
            <td class="monto">${{ number_format($detalle->comisiones, 2) }}</td>
            <td>Renta</td>
            <td class="monto">${{ number_format($detalle->renta, 2) }}</td>
        </tr>
        <tr>
            <td>Bonificaciones</td>
            <td class="monto">${{ number_format($detalle->bonificaciones, 2) }}</td>
            <td>Préstamos</td>
            <td class="monto">${{ number_format($detalle->prestamos, 2) }}</td>
           
        </tr>
        @if($detalle->anticipos > 0)
            <tr>
                <td></td>
                <td class="monto"></td>
                <td>Anticipos</td>
                <td class="monto">${{ number_format($detalle->anticipos, 2) }}</td>
            </tr>
        @endif
        <tr>
            <td>Otros Ingresos</td>
            <td class="monto">${{ number_format($detalle->otros_ingresos, 2) }}</td>
            <td>Otros Descuentos</td>
            <td class="monto">${{ number_format($detalle->otros_descuentos, 2) }}</td>
        </tr>
    </table>

    <div class="totales">
        <p><strong>Total Ingresos:</strong> ${{ number_format($totalIngresos, 2) }}</p>
        <p><strong>Total Deducciones:</strong> ${{ number_format($totalDeducciones, 2) }}</p>
        <p><strong>Neto a Pagar:</strong> ${{ number_format($detalle->sueldo_neto, 2) }}</p>
    </div>

    <div class="firma">
        <p>_____________________</p>
        <p>Firma del Empleado</p>
    </div>
</body>
</html>