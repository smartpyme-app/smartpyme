<!DOCTYPE html>
<html>
<head>
    {{-- <script language="javascript">setTimeout("self.close();",500)</script> --}}
    <title>Movimiento de una cuenta</title>
    <style>

        *{ font-size: 13px; margin: 0; padding: 0;}
        html, body{
            width: 18cm; height: 20cm;
            font-family: serif;
        }

        header > *, #totales > *{
            position: fixed!important;
            margin: 10px;
        }

        header{
            border: 1px solid black;
        }

        #empresa_nombre  { top: 0.5cm; left: 12.5cm;}
        #titulo_doc      {top: 1cm; left: 12cm;}
        #fechas_filtro  {top: 1.5cm; left: 11cm;}
        #fecha_actual   {top: 0.5cm; left: 20cm; }
        #hora_reporte    {top: 1.5cm; left: 20cm; }

        table   {position: relative; top: 3.5cm; left: 2.5cm; text-align: left; border-collapse: collapse;  margin-bottom: 10px;}
        table td{height: 0.5cm; text-align: left;}

        .id_partida{ width: 2.5cm; text-align: center;}
        .fecha_partida{ width: 2.5cm; text-align: center;}
        .concepto{ width: 9.5cm; text-align: left;}
        .cargo{ width: 3cm; text-align: center;}
        .abono{ width: 3cm; text-align: center;}
        .saldo{ width: 3cm; text-align: center;}


        .no_bord{
            border: none;
        }

        th {
            border: 1px solid black;
            padding: 5px;
        }

    </style>

    <style media="print"> .no-print{display: none; } </style>

</head>
<body>

<section id="factura">
    <header>
        <p id="empresa_nombre">{{$empresa->nombre}}</p>
        <p id="titulo_doc">Movimiento de una cuenta</p>
        <p id="fechas_filtro">Desde: {{$desde}} Hasta: {{$hasta}}</p>

        {{--        a la izquierda del documento--}}
        <p id="fecha_actual">{{$fecha}}</p>
        <p id="hora_reporte">{{$hora}}</p>
    </header>

    <div style="page-break-after:auto;">


            <table>
                <tr>
                    <th class="no_bord">Cuenta: </th>
                    <th class="no_bord">{{$cuenta_reporte->cuenta}}</th>
                    <th class="no_bord">{{$cuenta_reporte->nombre}}</th>
                    <th class="no_bord"></th>
                    <th class="no_bord">Saldo anterior: </th>
                    <th class="no_bord">{{$cuenta_reporte->saldo_anterior}}</th>
                </tr>
                <tr>
                    <th>Partida</th>
                    <th>Fecha</th>
                    <th>Concepto</th>
                    <th>Cargo</th>
                    <th>Abono</th>
                    <th>Saldo</th>
                </tr>

                @foreach($cuenta_reporte->detalles as $detalle)
                    <tr>
                        <td class="id_partida"> PART-{{$detalle->id_partida}}    </td>
                        <td class="fecha_partida">{{$detalle->created_at->toFormattedDateString()}}    </td>
                        <td class="concepto">     {{$detalle->concepto}}    </td>
                        <td class="cargo">        {{$detalle->debe}}   </td>
                        <td class="abono">        {{$detalle->haber}}    </td>
                        @if($cuenta_reporte->naturaleza=="Deudor")
                            <td class="saldo">    {{$cuenta_reporte->saldo_actual=$cuenta_reporte->saldo_actual+$detalle->debe-$detalle->haber}}   </td>
                        @else
                            <td class="saldo">     {{$cuenta_reporte->saldo_actual=$cuenta_reporte->saldo_actual-$detalle->debe+$detalle->haber}}   </td>
                        @endif

                    </tr>

                    @if($loop->last == false)

                    @endif
                @endforeach

                <tr>
                    <th></th>
                    <th></th>
                    <th>Total por cuenta:</th>
                    <th>{{$cuenta_reporte->cargo}}</th>
                    <th> {{$cuenta_reporte->abono}}</th>
                    <th>{{$cuenta_reporte->saldo_actual}}</th>
                </tr>

            </table>



</section>

</body>
</html>
