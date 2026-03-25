<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Boleta de Pago</title>
    <style>
        body { font-family: 'Arial', sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .company-info { margin-bottom: 20px; }
        .employee-info { margin-bottom: 20px; }
        .details { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .details th, .details td { 
            border: 1px solid #ddd; 
            padding: 8px;
            text-align: left;
        }
        .details th { background-color: #f5f5f5; }
        .totals { width: 100%; text-align: right; }
        .page-break { page-break-after: always; }
        .signature-line {
            margin-top: 50px;
            border-top: 1px solid #000;
            width: 200px;
            text-align: center;
            margin-left: auto;
            margin-right: auto;
        }
    </style>
</head>
<body>
    @foreach($detalles as $detalle)
    <div class="boleta">
        <div class="header">
            <h2>{{ $empresa->nombre }}</h2>
            <h3>BOLETA DE PAGO</h3>
            <p>Periodo: {{ date('d/m/Y', strtotime($planilla->fecha_inicio)) }} - 
                      {{ date('d/m/Y', strtotime($planilla->fecha_fin)) }}</p>
        </div>

        <div class="employee-info">
            <p><strong>Empleado:</strong> {{ $detalle->empleado->nombres }} {{ $detalle->empleado->apellidos }}</p>
            <p><strong>Código:</strong> {{ $detalle->empleado->codigo }}</p>
            <p><strong>DUI:</strong> {{ $detalle->empleado->dui }}</p>
            <p><strong>Cargo:</strong> {{ $detalle->empleado->cargo->nombre }}</p>
        </div>

        <table class="details">
            <tr>
                <th colspan="2">INGRESOS</th>
                <th colspan="2">DEDUCCIONES</th>
            </tr>
            <tr>
                <td>Salario Base</td>
                <td>${{ number_format($detalle->salario_base, 2) }}</td>
                <td>ISSS (3%)</td>
                <td>${{ number_format($detalle->isss_empleado, 2) }}</td>
            </tr>
            <tr>
                <td>Horas Extra</td>
                <td>${{ number_format($detalle->monto_horas_extra, 2) }}</td>
                <td>AFP (7.25%)</td>
                <td>${{ number_format($detalle->afp_empleado, 2) }}</td>
            </tr>
            <tr>
                <td>Comisiones</td>
                <td>${{ number_format($detalle->comisiones, 2) }}</td>
                <td>Renta</td>
                <td>${{ number_format($detalle->renta, 2) }}</td>
            </tr>
            <tr>
                <td>Bonificaciones</td>
                <td>${{ number_format($detalle->bonificaciones, 2) }}</td>
                <td>Préstamos</td>
                <td>${{ number_format($detalle->prestamos, 2) }}</td>
            </tr>
            @if($detalle->anticipos > 0)
            <tr>
                <td></td>
                <td></td>
                <td>Anticipos</td>
                <td>${{ number_format($detalle->anticipos, 2) }}</td>
            </tr>
            @endif
            <tr>
                <td>Otros Ingresos</td>
                <td>${{ number_format($detalle->otros_ingresos, 2) }}</td>
                <td>Otros Descuentos</td>
                <td>${{ number_format($detalle->otros_descuentos, 2) }}</td>
            </tr>
        </table>

        <div class="totals">
            <p><strong>Total Ingresos:</strong> ${{ number_format($detalle->total_ingresos, 2) }}</p>
            <p><strong>Total Deducciones:</strong> ${{ number_format($detalle->total_descuentos, 2) }}</p>
            <p><strong>Sueldo Neto:</strong> ${{ number_format($detalle->sueldo_neto, 2) }}</p>
        </div>

        <div class="signature-line">
            <p>Firma del Empleado</p>
        </div>

        @if(!$loop->last)
        <div class="page-break"></div>
        @endif
    </div>
    @endforeach
</body>
</html>