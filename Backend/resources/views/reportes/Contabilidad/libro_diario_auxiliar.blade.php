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

        #factura{
            margin-left: 0cm;
            margin-top: 0cm;
            position: relative;
        }

        .header > *, #totales > *{
            position: absolute;
            margin: 10px;
        }

        .header{
            border: 1px solid black;
        }


        #logo          {top: 0.5cm; left: 0.5cm }
        #empresa_nombre  { top: 0.5cm; left: 12.5cm;}
        #titulo_doc      {top: 1.5cm; left: 12cm;}
        #fechas_filtro  {top: 2.5cm; left: 11cm;}
        #fecha_actual   {top: 0.5cm; left: 20cm; }
        #hora_reporte    {top: 1.5cm; left: 20cm; }

        table   {position: absolute; top: 4.5cm; left: 2.5cm; text-align: left; border-collapse: collapse; }
        table td{height: 0.5cm; text-align: left;}

        .id_partida{ width: 1.5cm; text-align: center;}
        .fecha_partida{ width: 5cm; text-align: center;}
        .concepto{ width: 7cm; text-align: left;}
        .cargo{ width: 3cm; text-align: center;}
        .abono{ width: 3cm; text-align: center;}
        .saldo{ width: 3cm; text-align: center;}


        .no-print{position: absolute;}

        /*para el brake page */

        .page-break {
            page-break-before: always;
        }

        .invoice-articles-table {
            padding-bottom: 50px; //height of your footer
        }

        th {
            border: 1px solid black;
            padding: 5px;
        }

    </style>

    <style media="print"> .no-print{display: none; } </style>

</head>
<body>
<body>

<section id="factura">
    <div class="header">
{{--        a la derecha del documento --}}
        <p id="logo">{{$empresa->logo}}</p>

{{--        al centro del documento --}}
        <p id="empresa_nombre">{{$empresa->nombre}}</p>
        <p id="titulo_doc">Movimiento de una cuenta</p>
        <p id="fechas_filtro">Desde: {{$desde}} Hasta: {{$hasta}}</p>

{{--        a la izquierda del documento--}}
        <p id="fecha_actual">4/05/2024</p>
        <p id="hora_reporte">06:15:18 a.m.</p>

    </div>

    <div style="page-break-after:auto;">
    <table>
{{--            titulo de la cuenta--}}
{{--            <tr>Cuenta: {{ $num_cuenta }} - {{$nom_cuenta}}</tr>--}}

            @foreach($det_agrup as $cuentas)
                <tr>
                    <th>Partida</th>
                    <th>Fecha</th>
                    <th>Concepto</th>
                    <th>Cargo</th>
                    <th>Abono</th>
                    <th>Saldo</th>
                </tr>

                @foreach($cuentas as $detalle_par)
                    <tr>
                        <td class="id_partida">     {{$detalle_par->id_cuenta}}    </td>
                        <td class="fecha_partida">  {{$detalle_par->created_at}}    </td>
                        <td class="concepto">       {{$detalle_par->concepto}}    </td>
                        <td class="cargo">          {{$detalle_par->cargo}}   </td>
                        <td class="abono">          {{$detalle_par->abono}}    </td>
                        <td class="saldo">          {{$detalle_par->saldo}}   </td>
                    </tr>

{{--                    si el loop es multiplo de 30 ( es el numero que cabe dentro de la pagina) o si es el ultimo a iterar de la cuenta que le corresponde, aqui hace el salto de linea--}}

                    @if($loop->iteration % 30 === 0 or $loop->last == true)

                        </table>
                        <div class="page-break"></div>
                            @if($loop->last == false)
                                <div class="header">

                                    {{--        a la derecha del documento --}}
                                    <p id="logo">{{$empresa->logo}}</p>

                                    {{--        al centro del documento --}}
                                    <p id="empresa_nombre">{{$empresa->nombre}}</p>
                                    <p id="titulo_doc">Movimiento de una cuenta</p>
                                    <p id="fechas_filtro">Desde: {{$desde}} Hasta: {{$hasta}}</p>

                                    {{--        a la izquierda del documento--}}
                                    <p id="fecha_actual">4/05/2024</p>
                                    <p id="hora_reporte">06:15:18 a.m.</p>

                                </div>
                            @endif
                        <table class="table invoice-articles-table">

                        {{-- para que esto no aparezaca si es la ultima iteracion de las cuentas, si se coloca arriba del table da un error en dompdf--}}
                        @if($loop->last == false)
                            <thead>
                                <tr>
                                    <th>Partida</th>
                                    <th>Fecha</th>
                                    <th>Concepto</th>
                                    <th>Cargo</th>
                                    <th>Abono</th>
                                    <th>Saldo</th>
                                    ...
                            </thead>
                        @endif
                    @endif
                @endforeach
            @endforeach
    </table>

</section>

</body>
</html>
