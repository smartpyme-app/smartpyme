<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Planilla {{ $planilla->codigo }}</title>
    <style>
        body { 
            font-family: 'Arial', sans-serif; 
            font-size: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .info {
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 4px;
            text-align: left;
            font-size: 9px;
        }
        th {
            background-color: #f5f5f5;
        }
        .text-right {
            text-align: right;
        }
        .totales {
            margin-top: 20px;
            text-align: right;
        }
        .firma {
            margin-top: 50px;
            text-align: center;
        }
        .moneda {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ $empresa->nombre }}</h2>
        <h3>PLANILLA DE PAGO</h3>
        <p>Período: {{ date('d/m/Y', strtotime($planilla->fecha_inicio)) }} - 
                  {{ date('d/m/Y', strtotime($planilla->fecha_fin)) }}</p>
        <p>Código: {{ $planilla->codigo }}</p>
    </div>

    @php
        $simbolo = optional($empresa->currency)->currency_symbol ?? '$';
    @endphp

    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Empleado</th>
                <th>Salario Base</th>
                <th>Días Lab.</th>
                <th>H. Extra</th>
                <th>Comisiones</th>
                <th>Bonos</th>
                <th>Prestamos</th>
                <th>Anticipos</th>
                <th>Total Ingresos</th>
                <th>ISSS</th>
                <th>AFP</th>
                <th>Renta</th>
                <th>Otros Desc.</th>
                <th>Total Desc.</th>
                <th>Neto</th>
                <th>Viáticos</th>
                <th>Total a Pagar</th>
            </tr>
        </thead>
        <tbody>
            @foreach($detalles as $detalle)
            <tr>
                <td>{{ $detalle->empleado->codigo }}</td>
                <td>{{ $detalle->empleado->nombres }} {{ $detalle->empleado->apellidos }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->salario_base, 2) }}</td>
                <td class="text-right">{{ $detalle->dias_laborados }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->monto_horas_extra, 2) }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->comisiones, 2) }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->bonificaciones, 2) }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->prestamos, 2) }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->anticipos, 2) }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->total_ingresos, 2) }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->isss_empleado, 2) }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->afp_empleado, 2) }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->renta, 2) }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->otros_descuentos, 2) }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->total_descuentos, 2) }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->sueldo_neto, 2) }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->viaticos ?? 0, 2) }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format(($detalle->sueldo_neto ?? 0) + ($detalle->viaticos ?? 0), 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2"><strong>TOTALES</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum('salario_base'), 2) }}</strong></td>
                <td></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum('monto_horas_extra'), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum('comisiones'), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum('bonificaciones'), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum('prestamos'), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum('anticipos'), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum('total_ingresos'), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum('isss_empleado'), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum('afp_empleado'), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum('renta'), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum('otros_descuentos'), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum('total_descuentos'), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum('sueldo_neto'), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum(fn($d) => $d->viaticos ?? 0), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum(fn($d) => ($d->sueldo_neto ?? 0) + ($d->viaticos ?? 0)), 2) }}</strong></td>
            </tr>
        </tfoot>
    </table>

    <div class="firmas" style="margin-top: 50px;">
        <table style="width: 100%">
            <tr>
                <td style="width: 33%; text-align: center; border: none;">
                    _______________________<br>
                    Elaborado por
                </td>
                <td style="width: 33%; text-align: center; border: none;">
                    _______________________<br>
                    Revisado por
                </td>
                <td style="width: 33%; text-align: center; border: none;">
                    _______________________<br>
                    Autorizado por
                </td>
            </tr>
        </table>
    </div>
</body>
</html>