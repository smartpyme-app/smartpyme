<!DOCTYPE html>
<html>
<head>
    {{-- revisar--}}
    <title>Balance de General</title>
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
        #empresa_nombre      {top: 0.5cm; left: 7.1cm; font-size: medium; font-weight: bold;}
        #c_costos      {top: 1cm; left: 6.2cm; font-size: small; font-weight: bold;}
        #us_doll      {top: 1.5cm; left: 5.5cm; font-size:small; font-weight: normal; font-style: italic; }

        table   {position: absolute; top: 3cm; left: 1cm; text-align: left; border-collapse: collapse; }
        table td{height: 0.5cm; text-align: left;}

        .detalles{ width: 13cm; text-align: left;}
        .notas{ width: 2cm; text-align: center;}
        .año1{ width: 3cm; text-align: center;}
        .año2{ width: 3cm; text-align: center;}
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
            border-top: 5px solid black;
            padding: 5px;
            /*margin-bottom: 10px;*/
        }

    </style>

    <style media="print"> .no-print{display: none; } </style>

</head>
<body>
<body>

<section id="factura">
    <div class="header">

        {{--        al centro del documento --}}
        <p id="empresa_nombre"> NOMBRE DE LA EMPRESA S.A de C.V </p> {{-- aqui se debe colocar el mes --}}
        <p id="c_costos">Estado de Situación Financiera al -dia- de -mes- de -año-</p>
        <p id="us_doll">(Cifras Expresadas en Dólares de los Estados Unidos de America US $)</p>

    </div>

    <div style="page-break-after:auto;">
        <table>
            {{--            titulo de la cuenta--}}
            {{--            <tr>Cuenta: {{ $num_cuenta }} - {{$nom_cuenta}}</tr>--}}

            <tr>
                <th></th>
                <th>NOTAS</th>
                <th>AÑO 2023</th>
                <th>AÑO 2022</th>
            </tr>
            <tr>
                <td class="detalles">ACTIVOS</td>
                <td class="notas"></td>
                <td class="año1"></td>
                <td class="año2"></td>
            </tr>
            <tr>
                <td class="detalles">ACTIVOS CORRIENTES</td>
                <td class="notas"></td>
                <td class="año1"></td>
                <td class="año2"></td>
            </tr>
            <tr>
                <td class="detalles">EFECTIVOS Y EQUIVALENTES</td>
                <td class="notas"></td>
                <td class="año1">710,679.48</td>
                <td class="año2">395,895.10</td>
            </tr>

            {{--            ACTIVOS Y GASTOS--}}

{{--            @foreach($mayorizadas_deudoras as $detalle_par)--}}
{{--                <tr>--}}
{{--                    <td class="codigo">     {{$detalle_par['codigo']}}    </td>--}}
{{--                    <td class="nombre">  {{$detalle_par['nombre']}}    </td>--}}
{{--                    <td class="sal_inic">       {{$detalle_par['naturaleza_saldo']}}    </td>--}}
{{--                    <td class="cargo">          {{$detalle_par['cargo']}}   </td>--}}
{{--                    <td class="abono">          {{$detalle_par['abono']}}    </td>--}}
{{--                    <td class="sal_fin">          {{$detalle_par['saldo']}}   </td>--}}
{{--                </tr>--}}

{{--                --}}{{--                    si el loop es multiplo de 30 ( es el numero que cabe dentro de la pagina) o si es el ultimo a iterar de la cuenta que le corresponde, aqui hace el salto de linea--}}

{{--                @if($loop->iteration % 40 === 0 or $loop->last == true)--}}

{{--        </table>--}}
{{--        <div class="page-break"></div>--}}
{{--        <div class="header">--}}
{{--            --}}{{--        a la derecha del documento --}}
{{--            <p id="logo">{{$empresa->logo}}</p>--}}

{{--            --}}{{--        al centro del documento --}}
{{--            <p id="empresa_nombre">{{$empresa->nombre}}</p>--}}
{{--            <p id="titulo_balance">Balance de Comprobación a finde mes</p> --}}{{-- aqui se debe colocar el mes --}}
{{--            <p id="c_costos">Todos los Centros de Costos</p>--}}
{{--            <p id="us_doll">VALORES EXPRESADOS EN US DOLARES</p>--}}
{{--            <p id="naturaleza">ACTIVOS Y GASTOS</p>--}}


{{--        </div>--}}
{{--        <table class="table invoice-articles-table">--}}

{{--            --}}{{-- para que esto no aparezaca si es la ultima iteracion de las cuentas, si se coloca arriba del table da un error en dompdf--}}
{{--            @if($loop->last == false)--}}
{{--                <thead>--}}
{{--                <tr>--}}
{{--                    <th>Codigo</th>--}}
{{--                    <th>Nombre</th>--}}
{{--                    <th>Saldo Inicial</th>--}}
{{--                    <th>Cargo</th>--}}
{{--                    <th>Abono</th>--}}
{{--                    <th>Saldo Final</th>--}}
{{--                    ...--}}
{{--                </thead>--}}
{{--            @endif--}}
{{--            @endif--}}
{{--            @endforeach--}}


            {{--            PASIVOS Y PRODUCTOS --}}
{{--            @foreach($mayorizadas_acreedoras as $detalle_par)--}}
{{--                <tr>--}}
{{--                    <td class="codigo">     {{$detalle_par['codigo']}}    </td>--}}
{{--                    <td class="nombre">  Nombre de la cuenta    </td>--}}
{{--                    --}}{{--                    <td class="nombre">  {{$detalle_par->nombre}}    </td>--}}
{{--                    <td class="sal_inic">       {{$detalle_par['naturaleza_saldo']}}    </td>--}}
{{--                    <td class="cargo">          {{$detalle_par['cargo']}}   </td>--}}
{{--                    <td class="abono">          {{$detalle_par['abono']}}    </td>--}}
{{--                    <td class="sal_fin">          {{$detalle_par['saldo']}}   </td>--}}
{{--                </tr>--}}

{{--                --}}{{--                    si el loop es multiplo de 30 ( es el numero que cabe dentro de la pagina) o si es el ultimo a iterar de la cuenta que le corresponde, aqui hace el salto de linea--}}

{{--                @if($loop->iteration % 40 === 0 or $loop->last == true)--}}

{{--        </table>--}}
{{--        <div class="page-break"></div>--}}
{{--        <div class="header">--}}
{{--            --}}{{--        a la derecha del documento --}}
{{--            <p id="logo">{{$empresa->logo}}</p>--}}

{{--            --}}{{--        al centro del documento --}}
{{--            <p id="empresa_nombre">{{$empresa->nombre}}</p>--}}
{{--            <p id="titulo_balance">Balance de Comprobación a finde mes</p> --}}{{-- aqui se debe colocar el mes --}}
{{--            <p id="c_costos">Todos los Centros de Costos</p>--}}
{{--            <p id="us_doll">VALORES EXPRESADOS EN US DOLARES</p>--}}
{{--            <p id="naturaleza">ACTIVOS Y GASTOS</p>--}}


{{--        </div>--}}
{{--        <table class="table invoice-articles-table">--}}

{{--            --}}{{-- para que esto no aparezaca si es la ultima iteracion de las cuentas, si se coloca arriba del table da un error en dompdf--}}
{{--            @if($loop->last == false)--}}
{{--                <thead>--}}
{{--                <tr>--}}
{{--                    <th>Codigo</th>--}}
{{--                    <th>Nombre</th>--}}
{{--                    <th>Saldo Inicial</th>--}}
{{--                    <th>Cargo</th>--}}
{{--                    <th>Abono</th>--}}
{{--                    <th>Saldo Final</th>--}}
{{--                    ...--}}
{{--                </thead>--}}
{{--            @endif--}}
{{--            @endif--}}
{{--            @endforeach--}}


        </table>

</section>

</body>
</html>
