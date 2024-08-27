<!DOCTYPE html>
<html>
<head>
    {{-- <script language="javascript">setTimeout("self.close();",500)</script> --}}
    <title>Libro diario auxiliar </title>
    <style>

        *{ font-size: 13px; margin: 0; padding: 0;}
        html, body{
            width: 19.5cm; height: 20cm;
            font-family: serif;
            /*            border: 1px solid red;*/
        }

        /*#factura{*/
        /*    margin-left: 0cm;*/
        /*    margin-top: 0cm;*/
        /*    position: relative;*/
        /*}*/

        header > *, #totales > *{
            position: fixed!important;
            margin: 10px;
        }

        header{
            border: 1px solid black;
        }


        #logo          {top: 0.5cm; left: 0.5cm }
        #empresa_nombre  { top: 0.5cm; left: 12.5cm;}
        #titulo_doc      {top: 1.5cm; left: 12cm;}
        #fechas_filtro  {top: 2.5cm; left: 11cm;}
        #fecha_actual   {top: 0.5cm; left: 20cm; }
        #hora_reporte    {top: 1.5cm; left: 20cm; }

        table   {position: relative; top: 4.5cm; left: 2.5cm; text-align: left; border-collapse: collapse;  margin-bottom: 10px;}
        table td{height: 0.5cm; text-align: left;}

        .id_partida{ width: 1.5cm; text-align: center;}
        .fecha_partida{ width: 3.5cm; text-align: center;}
        .concepto{ width: 9.5cm; text-align: left;}
        .cargo{ width: 3cm; text-align: center;}
        .abono{ width: 3cm; text-align: center;}
        .saldo{ width: 3cm; text-align: center;}


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
        <p id="fecha_actual">4/05/2024</p>
        <p id="hora_reporte">06:15:18 a.m.</p>
    </header>

    <div style="page-break-after:auto;">

{{--            titulo de la cuenta--}}
{{--            <tr>Cuenta: {{ $num_cuenta }} - {{$nom_cuenta}}</tr>--}}

            @foreach($cuentas as $cuenta)
{{--falta colocarle lo siguiente--}}
{{--        encabezado--}}
{{--        saldo anterior--}}
            <table>
                <tr>
                    <th>Cuenta: </th>
                    <th>{{$cuenta->cuenta}}</th>
                    <th></th>
                    <th></th>
                    <th>Saldo anterior: </th>
                    <th>{{$cuenta->saldo_anterior}}</th>
                </tr>
                <tr>
                    <th>Partida</th>
                    <th>Fecha</th>
                    <th>Concepto</th>
                    <th>Cargo</th>
                    <th>Abono</th>
                    <th>Saldo</th>
                </tr>

                @foreach($cuenta->detalles as $detalle)
                    <tr>
                        <td class="id_partida">   {{$detalle->codigo}}    </td>
                        <td class="fecha_partida">{{$detalle->created_at->toFormattedDateString()}}    </td>
                        <td class="concepto">     {{$detalle->concepto}}    </td>
                        <td class="cargo">        {{$detalle->debe}}   </td>
                        <td class="abono">        {{$detalle->haber}}    </td>
                        @if($cuenta->naturaleza=="Deudor")
                            <td class="saldo">    {{$cuenta->saldo_actual=$cuenta->saldo_actual+$detalle->debe-$detalle->haber}}   </td>
                        @else
                            <td class="saldo">     {{$cuenta->saldo_actual=$cuenta->saldo_actual-$detalle->debe+$detalle->haber}}   </td>
                        @endif

                    </tr>

                        @if($loop->last == false)

                        @endif
                @endforeach

                <tr>
                    <th></th>
                    <th></th>
                    <th>Total por cuenta:</th>
                    <th>{{$cuenta->cargo}}</th>
                    <th> {{$cuenta->abono}}</th>
                    <th>{{$cuenta->saldo_actual}}</th>
                </tr>

            </table>
            @endforeach


</section>

</body>
</html>
