<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balance de Comprobación - {{$periodo}}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .company-info {
            text-align: center;
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .report-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .period-info {
            font-size: 12px;
            margin-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .numeric {
            text-align: right;
        }
        .total-row {
            font-weight: bold;
            background-color: #f9f9f9;
        }
        .deudor {
            color: #0066cc;
        }
        .acreedor {
            color: #cc0000;
        }
        .estado-cerrado {
            color: #009900;
            font-weight: bold;
        }
        .estado-abierto {
            color: #ff6600;
            font-weight: bold;
        }
        .resumen {
            margin-top: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
        }
        .resumen-title {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .cuadre-ok {
            color: #009900;
            font-weight: bold;
        }
        .cuadre-error {
            color: #cc0000;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <div class="company-name">{{$empresa->nombre_empresa}}</div>
            <div>{{$empresa->direccion}}</div>
            <div>Tel: {{$empresa->telefono}} | Email: {{$empresa->email}}</div>
        </div>

        <div class="report-title">BALANCE DE COMPROBACIÓN</div>
        <div class="period-info">Período: {{$periodo}}</div>
        <div class="period-info">Estado:
            @if($balance['totales']['cuadra'])
                <span class="cuadre-ok">CUADRADO</span>
            @else
                <span class="cuadre-error">DESCUADRADO</span>
            @endif
        </div>
        <div class="period-info">Fecha de generación: {{date('d/m/Y H:i:s')}}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 10%;">Código</th>
                <th style="width: 30%;">Cuenta</th>
                <th style="width: 8%;">Naturaleza</th>
                <th style="width: 13%;">Saldo Inicial</th>
                <th style="width: 13%;">Debe</th>
                <th style="width: 13%;">Haber</th>
                <th style="width: 13%;">Saldo Final</th>
                <th style="width: 8%;">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($balance['balance'] as $cuenta)
                <tr>
                    <td>{{$cuenta['codigo']}}</td>
                    <td>{{$cuenta['nombre']}}</td>
                    <td class="{{$cuenta['naturaleza'] == 'Deudor' ? 'deudor' : 'acreedor'}}">
                        {{$cuenta['naturaleza']}}
                    </td>
                    <td class="numeric">{{number_format($cuenta['saldo_inicial'], 2)}}</td>
                    <td class="numeric">{{number_format($cuenta['debe'], 2)}}</td>
                    <td class="numeric">{{number_format($cuenta['haber'], 2)}}</td>
                    <td class="numeric {{$cuenta['naturaleza'] == 'Deudor' ? 'deudor' : 'acreedor'}}">
                        {{number_format($cuenta['saldo_final'], 2)}}
                    </td>
                    <td class="{{$cuenta['estado'] == 'Cerrado' ? 'estado-cerrado' : 'estado-abierto'}}">
                        {{$cuenta['estado']}}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="resumen">
        <div class="resumen-title">RESUMEN DEL BALANCE</div>
        <table style="margin-top: 10px;">
            <tr>
                <td><strong>Total Saldos Deudores:</strong></td>
                <td class="numeric deudor"><strong>{{number_format($balance['totales']['deudor'], 2)}}</strong></td>
            </tr>
            <tr>
                <td><strong>Total Saldos Acreedores:</strong></td>
                <td class="numeric acreedor"><strong>{{number_format($balance['totales']['acreedor'], 2)}}</strong></td>
            </tr>
            <tr>
                <td><strong>Diferencia:</strong></td>
                <td class="numeric {{$balance['totales']['diferencia'] == 0 ? 'cuadre-ok' : 'cuadre-error'}}">
                    <strong>{{number_format($balance['totales']['diferencia'], 2)}}</strong>
                </td>
            </tr>
            <tr>
                <td><strong>Estado del Balance:</strong></td>
                <td class="{{$balance['totales']['cuadra'] ? 'cuadre-ok' : 'cuadre-error'}}">
                    <strong>{{$balance['totales']['cuadra'] ? 'CUADRADO' : 'DESCUADRADO'}}</strong>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Este reporte fue generado automáticamente por el sistema SmartPYME</p>
        <p>Los saldos mostrados corresponden al período {{$periodo}} y reflejan el estado al momento del cierre</p>
    </div>
</body>
</html>
