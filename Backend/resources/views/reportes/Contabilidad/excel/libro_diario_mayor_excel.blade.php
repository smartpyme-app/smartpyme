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
            left: 9.5cm;
        }

        #titulo_doc {
            top: 1cm;
            left: 9.5cm;
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
<table>
    <thead>
    <tr>
        <th colspan="6" style="text-align: center; font-size: 16px;"><strong>Reporte Libro Diario Mayor</strong></th>
    </tr>
    <tr>
        <th colspan="6" style="text-align: center; font-size: 16px;"><strong>Empresa: {{ $empresa->nombre }}</strong>
        </th>
    </tr>
    <tr>
        <th colspan="6" style="text-align: center; font-size: 16px;"><strong>Periodo: {{$month_name}} - {{$year}}</strong></th>
    </tr>
    <tr>
        <th colspan="6" style="text-align: center; font-size: 16px;"><strong>VALORES ESPRESADOS EN US DOLARES</strong></th>
    </tr>
    <tr></tr>
    </thead>
    <tbody>
    @foreach($cuentas as $cuenta)

        {{--@if($cuenta->detalles != null)--}}
        <table>
            <tr>
                <th style="border: none; font-size: 14px;"><strong>Cuenta:</strong></th>
                <th style="border: none; font-size: 14px;"><strong>{{$cuenta->cuenta}}</strong></th>
                <th style="border: none; font-size: 14px;"><strong>{{$cuenta->nombre}}</strong></th>
                <th style="border: none; "></th>
                <th style="border: none; "></th>
                <th style="border: none; "></th>
            </tr>
            <tr>
                <th style="font-size: 14px;border: 1px solid #000;"><strong>Partida</strong></th>
                <th style="font-size: 14px;border: 1px solid #000;"><strong>Fecha</strong></th>
                <th style="font-size: 14px;border: 1px solid #000;"><strong>Concepto</strong></th>
                <th style="font-size: 14px;border: 1px solid #000;"><strong>Cargo</strong></th>
                <th style="font-size: 14px;border: 1px solid #000;"><strong>Abono</strong></th>
                <th style="font-size: 14px;border: 1px solid #000;"><strong>Saldo</strong></th>
            </tr>

            <tr>
                <td style="border: none;"></td>
                <td style="border: none;"></td>
                <td style="border: none;">Saldo inicial:</td>
                <td style="border: none; text-align: center;">0.00</td>
                <td style="border: none; text-align: center;">0.00</td>
                <td style="border: none; text-align: center;">{{$cuenta->saldo_anterior}}</td>
            </tr>
            @foreach($cuenta->detalles as $detalle)
                <tr>
                    <td class="id_partida"> PART - {{$detalle->id_partida}}    </td>
                    <td class="fecha_partida">  {{$detalle->created_at}}    </td>
                    <td class="concepto">       {{$detalle->concepto}}    </td>
                    <td class="cargo">          {{$detalle->debe}}   </td>
                    <td class="abono">          {{$detalle->haber}}    </td>
                    {{--                        <td class="saldo">          {{$detalle->saldo}}   </td>--}}
                    @if($cuenta->naturaleza=="Deudor")
                        <td class="saldo">    {{$cuenta->saldo_actual = number_format((float)$cuenta->saldo_actual + (float)$detalle->debe - (float)$detalle->haber, 2) }}</td>
                    @else
                        <td class="saldo">     {{$cuenta->saldo_actual=number_format((float)$cuenta->saldo_actual-(float)$detalle->debe+(float)$detalle->haber, 2)}}</td>
                    @endif
                </tr>
                @if($loop->last == false)

                @endif

            @endforeach
            {{--                @endif--}}
            <tr>
                <th></th>
                <th></th>
                <th><strong>Total por cuenta:</strong></th>
                <th><strong>{{number_format($cuenta->cargo,2)}}</strong></th>
                <th><strong>{{number_format($cuenta->saldo_actual,2)}}</strong></th>
            </tr>
            <tr></tr>
        </table>
    @endforeach
    </tbody>
</table>
</body>
</html>
