<!DOCTYPE html>
<html>
<head>
    <title>Reporte Libro Diario Mayor</title>
    <style>

        * {
            font-size: 13px;
            margin: 0;
            padding: 0;
        }

        html, body {
            width: 19.5cm;
            height: 20cm;
            font-family: serif;
            /*            border: 1px solid red;*/
        }

        #factura {
            margin-left: 0cm;
            margin-top: 0cm;
            position: relative;
        }

        .header > *, #totales > * {
            position: absolute;
            margin: 10px;
        }

        .header {
            border: 1px solid black;
        }


        #logo {
            top: 0.5cm;
            left: 0.5cm
        }

        #empresa_nombre {
            top: 0.5cm;
            left: 8.5cm;
        }

        #titulo_doc {
            top: 1cm;
            left: 8.5cm;
            font-weight: bold;
        }

        #fechas_filtro {
            top: 1.5cm;
            left: 8.5cm;
        }

        #centro_costos {
            top: 2cm;
            left: 9.2cm;
        }

        #val_dolares {
            top: 2.5cm;
            left: 8cm;
        }

        #fecha_actual {
            top: 0.5cm;
            left: 17cm;
        }

        #hora_reporte {
            top: 1.5cm;
            left: 17cm;
        }

        table {
            position: relative;
            top: 4cm;
            left: 0.5cm;
            text-align: left;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        table td {
            height: 0.5cm;
            text-align: left;
        }

        .id_partida {
            width: 3.5cm;
            text-align: center;
        }

        .fecha_partida {
            width: 3.5cm;
            text-align: center;
        }

        .concepto {
            width: 7.5cm;
            text-align: left;
        }

        .cargo {
            width: 2cm;
            text-align: center;
        }

        .abono {
            width: 2cm;
            text-align: center;
        }

        .saldo {
            width: 2cm;
            text-align: center;
        }


        .no-print {
            position: absolute;
        }

        /*para el brake page */

        .page-break {
            page-break-before: always;
        }

        .invoice-articles-table {
            padding-bottom: 50px;
        / / height of your footer
        }

        th {
            border: 1px solid black;
            padding: 5px;
        }

    </style>

    <style media="print"> .no-print {
            display: none;
        } </style>

</head>
<body>
<section id="factura">
    <div class="header">
        <p id="titulo_doc">Reporte Libro Diario Mayor</p>
        <p id="empresa_nombre">{{$empresa->nombre}}</p>
        <p id="fechas_filtro">Periodo: {{$month_name}} - {{$year}}</p>
        <p id="val_dolares">VALORES ESPRESADOS EN US DOLARES</p>
    </div>
    <div style="page-break-after:auto;">
        @foreach($cuentas as $cuenta)

            {{--@if($cuenta->detalles != null)--}}
            <table>
                <tr>
                    <th style="border: none; ">Cuenta:</th>
                    <th style="border: none; ">{{$cuenta->cuenta}}</th>
                    <th style="border: none; ">{{$cuenta->nombre}}</th>
                    <th style="border: none; "></th>
                    <th style="border: none; "></th>
                    <th style="border: none; "></th>
                    <th style="border: none; "></th>
                </tr>
                <tr>
                    <th>Partida</th>
                    <th>Correlativo</th>
                    <th>Fecha</th>
                    <th>Concepto</th>
                    <th>Cargo</th>
                    <th>Abono</th>
                    <th>Saldo</th>
                </tr>

                <tr>
                    <td style="border: none;"></td>
                    <td style="border: none;"></td>
                    <td style="border: none;"></td>
                    <td style="border: none;">Saldo inicial:</td>
                    <td style="border: none; text-align: center;">0.00</td>
                    <td style="border: none; text-align: center;">0.00</td>
                    <td style="border: none; text-align: center;">{{ number_format($cuenta->saldo_anterior ?? 0, 2) }}</td>
                </tr>
                @foreach($cuenta->detalles as $detalle)
                    <tr>
                        <td class="id_partida"> PART - {{$detalle->id_partida}}    </td>
                        <td class="correlativo">{{ $detalle->partida->correlativo ?? '' }}</td>
                        <td class="fecha_partida">  {{$detalle->created_at}}    </td>
                        <td class="concepto">       {{$detalle->concepto}}    </td>
                        <td class="cargo">          {{ number_format($detalle->debe ?? 0, 2) }}   </td>
                        <td class="abono">          {{ number_format($detalle->haber ?? 0, 2) }}    </td>
                        <td class="saldo">{{ number_format($detalle->saldo_calculado ?? 0, 2) }}</td>
                    </tr>
                    @if($loop->last == false)

                    @endif

                @endforeach
                {{--                @endif--}}
                <tr>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th>Total por cuenta:</th>
                    <th>{{ number_format($cuenta->cargo ?? 0, 2) }}</th>
                    <th>{{ number_format($cuenta->abono ?? 0, 2) }}</th>
                    <th>{{ number_format($cuenta->saldo_actual ?? 0, 2) }}</th>
                </tr>
            </table>
        @endforeach
    </div>
</section>
</body>
</html>
