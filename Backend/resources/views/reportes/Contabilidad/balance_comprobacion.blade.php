<!DOCTYPE html>
<html>
<head>
    {{-- revisar--}}
    <title>Balance de comprobación</title>
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
        #empresa_nombre  { top: 0.5cm; left: 9.5cm;}
        #titulo_balance      {top: 1cm; left: 8.3cm;}
        #c_costos      {top: 1.5cm; left: 9cm;}
        #us_doll      {top: 2cm; left: 7.5cm;}
        #naturaleza  {top: 2.5cm; left: 9.5cm;}
        #fecha_actual   {top: 0.5cm; left: 17cm; }
        #hora_reporte    {top: 1.5cm; left: 17cm; }

        table   {position: absolute; top: 4.5cm; left: 0.5cm; text-align: left; border-collapse: collapse; }
        table td{height: 0.5cm; text-align: left;}

        .codigo{ width: 2.5cm; text-align: left;}
        .sal_inic{ width: 2cm; text-align: center;}
        .nombre{ width: 10cm; text-align: left;}
        .cargo{ width: 2cm; text-align: center;}
        .abono{ width: 2cm; text-align: center;}
        .sal_fin{ width: 2cm; text-align: center;}


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
       <p id="titulo_balance">Balance de Comprobación a finde mes</p> {{-- aqui se debe colocar el mes --}}
        <p id="c_costos">Todos los Centros de Costos</p>
        <p id="us_doll">VALORES EXPRESADOS EN US DOLARES</p>
        <p id="naturaleza">ACTIVOS Y GASTOS</p>


    </div>

    <div style="page-break-after:auto;">
        <table>
            {{--            titulo de la cuenta--}}
            {{--            <tr>Cuenta: {{ $num_cuenta }} - {{$nom_cuenta}}</tr>--}}


                <tr>
                    <th>Codigo</th>
                    <th>Nombre</th>
                    <th>Saldo inicial</th>
                    <th>Cargos</th>
                    <th>Abonos</th>
                    <th>Saldo Final</th>
                </tr>

{{--            ACTIVOS Y GASTOS--}}

                @foreach($cuentas_deudoras as $detalle_par)
                    <tr>
                        <td class="codigo">     {{$detalle_par->codigo}}    </td>
                        <td class="nombre">  {{$detalle_par->nombre}}    </td>
                        <td class="sal_inic">       {{$detalle_par->naturaleza}}    </td>
                        <td class="cargo">          {{$detalle_par->rubro}}   </td>
                        <td class="abono">          {{$detalle_par->nivel}}    </td>
                        <td class="sal_fin">          {{$detalle_par->id_empresa}}   </td>
                    </tr>

                    {{--                    si el loop es multiplo de 30 ( es el numero que cabe dentro de la pagina) o si es el ultimo a iterar de la cuenta que le corresponde, aqui hace el salto de linea--}}

                    @if($loop->iteration % 40 === 0 or $loop->last == true)

        </table>
        <div class="page-break"></div>
        <div class="header">
            {{--        a la derecha del documento --}}
            <p id="logo">{{$empresa->logo}}</p>

            {{--        al centro del documento --}}
            <p id="empresa_nombre">{{$empresa->nombre}}</p>
            <p id="titulo_balance">Balance de Comprobación a finde mes</p> {{-- aqui se debe colocar el mes --}}
            <p id="c_costos">Todos los Centros de Costos</p>
            <p id="us_doll">VALORES EXPRESADOS EN US DOLARES</p>
            <p id="naturaleza">ACTIVOS Y GASTOS</p>


        </div>
        <table class="table invoice-articles-table">

            {{-- para que esto no aparezaca si es la ultima iteracion de las cuentas, si se coloca arriba del table da un error en dompdf--}}
            @if($loop->last == false)
                <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Nombre</th>
                    <th>Saldo Inicial</th>
                    <th>Cargo</th>
                    <th>Abono</th>
                    <th>Saldo Final</th>
                    ...
                </thead>
            @endif
            @endif
            @endforeach


{{--            PASIVOS Y PRODUCTOS --}}
                @foreach($cuentas_acreedoras as $detalle_par)
                <tr>
                    <td class="codigo">     {{$detalle_par->codigo}}    </td>
                    <td class="nombre">  {{$detalle_par->nombre}}    </td>
                    <td class="sal_inic">       {{$detalle_par->naturaleza}}    </td>
                    <td class="cargo">          {{$detalle_par->rubro}}   </td>
                    <td class="abono">          {{$detalle_par->nivel}}    </td>
                    <td class="sal_fin">          {{$detalle_par->id_empresa}}   </td>
                </tr>

                {{--                    si el loop es multiplo de 30 ( es el numero que cabe dentro de la pagina) o si es el ultimo a iterar de la cuenta que le corresponde, aqui hace el salto de linea--}}

                @if($loop->iteration % 40 === 0 or $loop->last == true)

        </table>
        <div class="page-break"></div>
        <div class="header">
            {{--        a la derecha del documento --}}
            <p id="logo">{{$empresa->logo}}</p>

            {{--        al centro del documento --}}
            <p id="empresa_nombre">{{$empresa->nombre}}</p>
            <p id="titulo_balance">Balance de Comprobación a finde mes</p> {{-- aqui se debe colocar el mes --}}
            <p id="c_costos">Todos los Centros de Costos</p>
            <p id="us_doll">VALORES EXPRESADOS EN US DOLARES</p>
            <p id="naturaleza">ACTIVOS Y GASTOS</p>


        </div>
        <table class="table invoice-articles-table">

            {{-- para que esto no aparezaca si es la ultima iteracion de las cuentas, si se coloca arriba del table da un error en dompdf--}}
            @if($loop->last == false)
                <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Nombre</th>
                    <th>Saldo Inicial</th>
                    <th>Cargo</th>
                    <th>Abono</th>
                    <th>Saldo Final</th>
                    ...
                </thead>
            @endif
            @endif
            @endforeach


        </table>

</section>

</body>
</html>
