<!DOCTYPE html>
<html>
<head>
    <title>Balance de comprobación</title>
    <style>
        * {
            font-size: 11px;
            margin: 0;
            padding: 0;
        }

        html, body {
            width: 19.5cm;
            height: 20cm;
            font-family: serif;
        }

        #factura {
            margin-left: 0cm;
            margin-top: 0cm;
            position: relative;
        }

        .header {
            text-align: center;
            font-weight: bold;
            margin-bottom: 20px;
            position: relative;
            padding-top: 70px;
        }

        #logo {
            position: absolute;
            left: 1cm;
            top: 20px;
            width: 100px;
        }

        #logo img {
            max-width: 100%;
            height: auto;
        }

        table {
            position: absolute;
            top: 4.5cm;
            left: 0.5cm;
            text-align: left;
            border-collapse: collapse;
            width: 18cm;
        }

        table td, table th {
            height: 0.4cm;
            text-align: center;
            border: 1px solid black;
            padding: 2px;
            font-size: 10px;
        }

        .codigo {
            width: 2cm;
        }

        .nombre {
            width: 5cm;
        }

        .naturaleza {
            width: 1.5cm;
            text-align: center;
            font-size: 9px;
        }

        .sal_inic, .cargo, .abono, .sal_fin {
            width: 2.5cm;
            text-align: right;
        }

        .totales {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        .diferencia {
            background-color: #e0e0e0;
            font-weight: bold;
        }

        .no-print {
            position: absolute;
        }

        .page-break {
            page-break-before: always;
        }

        .invoice-articles-table {
            padding-bottom: 50px;
        }

        th {
            border: 1px solid black;
            padding: 5px;
            font-weight: bold;
            background-color: #f9f9f9;
        }

        .cuentas-padre {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .nota-explicativa {
            background-color: #fff3cd;
            font-style: italic;
            font-size: 9px;
        }
    </style>

    <style media="print">
        .no-print {
            display: none;
        }
    </style>
</head>
<body>

<section id="factura">
    <div class="header">
        <p id="empresa_nombre">{{$empresa->nombre}}</p>
        <h2 id="titulo_balance">Balance de Comprobación</h2>
        <p id="periodo">Periodo: {{$month_name}} - {{$year}}</p>
        <p id="c_costos">Todos los Centros de Costos</p>
        <p id="us_doll">VALORES EXPRESADOS EN US DOLARES</p>
        <p id="naturaleza">ACTIVOS Y GASTOS</p>
    </div>

    <div style="page-break-after:auto;">
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Naturaleza</th>
                    <th>Saldo Inicial</th>
                    <th>Cargos</th>
                    <th>Abonos</th>
                    <th>Saldo Final</th>
                </tr>
            </thead>
            <tbody>
                @foreach($balance as $detalle_par)
                    <tr class="{{ $detalle_par['es_cuenta_padre'] ? 'cuentas-padre' : '' }}">
                        <td class="codigo">{{ $detalle_par['codigo'] }}{{ $detalle_par['es_cuenta_padre'] ? ' (P)' : '' }}</td>
                        <td class="nombre">{{ $detalle_par['nombre'] }}</td>
                        <td class="naturaleza">{{ $detalle_par['naturaleza'] ?? 'N/A' }}</td>
                        <td class="sal_inic">{{ number_format($detalle_par['saldo_inicial'], 2) }}</td>
                        <td class="cargo">{{ number_format($detalle_par['debe'], 2) }}</td>
                        <td class="abono">{{ number_format($detalle_par['haber'], 2) }}</td>
                        <td class="sal_fin">{{ number_format($detalle_par['saldo_final'], 2) }}</td>
                    </tr>

                    @if($loop->iteration % 35 === 0 && !$loop->last)
                        </tbody>
                        </table>
                        <div class="page-break"></div>

                        <div class="header">
                            <p id="empresa_nombre">{{$empresa->nombre}}</p>
                            <h2 id="titulo_balance">Balance de Comprobación (Continuación)</h2>
                            <p id="periodo">Periodo: {{$month_name}} - {{$year}}</p>
                            <p id="c_costos">Todos los Centros de Costos</p>
                            <p id="us_doll">VALORES EXPRESADOS EN US DOLARES</p>
                            <p id="naturaleza">ACTIVOS Y GASTOS</p>
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Nombre</th>
                                    <th>Naturaleza</th>
                                    <th>Saldo Inicial</th>
                                    <th>Cargos</th>
                                    <th>Abonos</th>
                                    <th>Saldo Final</th>
                                </tr>
                            </thead>
                            <tbody>
                    @endif
                @endforeach

                @if(isset($totales))
                    <tr>
                        <td colspan="7"></td>
                    </tr>
                    <tr class="nota-explicativa">
                        <td colspan="7" style="text-align: center;">NOTA: Los totales solo incluyen cuentas padre (nivel = 0)</td>
                    </tr>
                    <tr class="nota-explicativa">
                        <td colspan="7" style="text-align: center;">Las cuentas padre (P) consolidan los valores de sus subcuentas</td>
                    </tr>
                    <tr style="border-top: 2px solid black;">
                        <td colspan="7"></td>
                    </tr>
                    <tr class="totales">
                        <td colspan="3" style="text-align: center; font-weight: bold;">TOTALES</td>
                        <td style="text-align: right; font-weight: bold;">{{ number_format($totales['saldo_inicial'], 2) }}</td>
                        <td style="text-align: right; font-weight: bold;">{{ number_format($totales['debe'], 2) }}</td>
                        <td style="text-align: right; font-weight: bold;">{{ number_format($totales['haber'], 2) }}</td>
                        <td style="text-align: right; font-weight: bold;">{{ number_format($totales['saldo_final'], 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="7"></td>
                    </tr>
                    <tr class="diferencia">
                        <td colspan="5" style="text-align: center; font-weight: bold;">DIFERENCIA (Debe - Haber)</td>
                        <td style="text-align: right; font-weight: bold;">{{ number_format($totales['diferencia'], 2) }}</td>
                        <td></td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</section>

</body>
</html>
