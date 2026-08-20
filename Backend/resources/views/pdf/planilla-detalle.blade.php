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
                <th>Días</th>
                <th>Salario Dev.</th>
                <th>H. Extra</th>
                <th>Comisiones</th>
                <th>Bonos</th>
                <th>Otros Ing.</th>
                <th>Abonos</th>
                <th>Total Ingresos</th>
                <th>ISSS</th>
                <th>AFP</th>
                <th>Renta</th>
                <th>Préstamos</th>
                <th>Anticipos</th>
                <th>Otros Desc.</th>
                <th>Total Desc.</th>
                <th>Sueldo Neto</th>
                <th>Viáticos</th>
                <th>Total a Pagar</th>
            </tr>
        </thead>
        <tbody>
            @foreach($detalles as $detalle)
            @php
                $salDev = (float)($detalle->salario_devengado ?? $detalle->salario_base ?? 0);
                $totIng = (float)($detalle->total_ingresos ?? 0);
                $totDesc = (float)($detalle->total_descuentos ?? 0);
                $neto = (float)($detalle->sueldo_neto ?? ($totIng - $totDesc));
                $viaticos = (float)($detalle->viaticos ?? 0);
                $aPagar = $neto + $viaticos;
            @endphp
            <tr>
                <td>{{ $detalle->empleado->codigo ?? '' }}</td>
                <td>{{ $detalle->empleado->nombres ?? '' }} {{ $detalle->empleado->apellidos ?? '' }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->salario_base, 2) }}</td>
                <td class="text-right">{{ $detalle->dias_laborados ?? 30 }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($salDev, 2) }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->monto_horas_extra ?? 0, 2) }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->comisiones ?? 0, 2) }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->bonificaciones ?? 0, 2) }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->otros_ingresos ?? 0, 2) }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->abonos ?? 0, 2) }}</td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($totIng, 2) }}</strong></td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->isss_empleado ?? $detalle->isss ?? 0, 2) }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->afp_empleado ?? $detalle->afp ?? 0, 2) }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->renta ?? 0, 2) }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->prestamos ?? 0, 2) }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($detalle->anticipos ?? 0, 2) }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format(($detalle->otros_descuentos ?? 0) + ($detalle->descuentos_judiciales ?? 0), 2) }}</td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($totDesc, 2) }}</strong></td>
                <td class="moneda">{{ $simbolo }}{{ number_format($neto, 2) }}</td>
                <td class="moneda">{{ $simbolo }}{{ number_format($viaticos, 2) }}</td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($aPagar, 2) }}</strong></td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2"><strong>TOTALES</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum('salario_base'), 2) }}</strong></td>
                <td></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum(fn($d) => $d->salario_devengado ?? $d->salario_base ?? 0), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum('monto_horas_extra'), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum('comisiones'), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum('bonificaciones'), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum('otros_ingresos'), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum('abonos'), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum('total_ingresos'), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum(fn($d) => $d->isss_empleado ?? $d->isss ?? 0), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum(fn($d) => $d->afp_empleado ?? $d->afp ?? 0), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum('renta'), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum('prestamos'), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum('anticipos'), 2) }}</strong></td>
                <td class="moneda"><strong>{{ $simbolo }}{{ number_format($detalles->sum(fn($d) => ($d->otros_descuentos ?? 0) + ($d->descuentos_judiciales ?? 0)), 2) }}</strong></td>
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