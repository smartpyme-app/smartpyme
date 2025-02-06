<!DOCTYPE html>
<html>
<head>
    {{-- revisar--}}
    <title>Balance de comprobación</title>
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
            padding-top: 70px; /* Increased padding */
        }

        #logo {
            position: absolute;
            left: 1cm;
            top: 20px; /* Adjusted top position */
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
        }

        table td {
            height: 0.5cm;
            text-align: left;
        }

        .codigo {
            width: 2.5cm;
            text-align: left;
        }

        .sal_inic {
            width: 2cm;
            text-align: center;
        }

        .nombre {
            width: 10cm;
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

        .sal_fin {
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
        {{--        a la derecha del documento --}}
{{--        <p id="logo"><img src="{{$empresa->logo}}" /></p>--}}

        {{--        al centro del documento --}}
        <p id="empresa_nombre">{{$empresa->nombre}}</p>
        <h2 id="titulo_balance">Balance de Comprobación</h2>
        <p id="periodo">Periodo: {{$month_name}} - {{$year}}</p>
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
                <th>Saldo Inicial</th>
                <th>Cargos</th>
                <th>Abonos</th>
                <th>Saldo Final</th>
            </tr>

            {{--            ACTIVOS Y GASTOS--}}

            @foreach($balance as $detalle_par)
                <tr>
                    <td class="codigo">     {{$detalle_par['codigo']}}    </td>
                    <td class="nombre">  {{$detalle_par['nombre']}}    </td>
                    <td class="sal_inic">       {{$detalle_par['saldo_inicial']}}    </td>
                    <td class="cargo">          {{$detalle_par['debe']}}   </td>
                    <td class="abono">          {{$detalle_par['haber']}}    </td>
                    <td class="sal_fin">          {{$detalle_par['saldo_final']}}   </td>
                </tr>

                {{--                    si el loop es multiplo de 30 ( es el numero que cabe dentro de la pagina) o si es el ultimo a iterar de la cuenta que le corresponde, aqui hace el salto de linea--}}

                @if($loop->iteration % 40 === 0 or $loop->last == true)

        </table>
        <div class="page-break"></div>
        <!-- <div class="header">
            {{--        a la derecha del documento --}}
            <p id="logo">{{$empresa->logo}}</p>

            {{--        al centro del documento --}}
            <p id="empresa_nombre">{{$empresa->nombre}}</p>
            <h2 id="titulo_balance">Balance de Comprobación a finde mes</h2>
            <p id="periodo">Periodo: {{$month_name}} - {{$year}}</p>
            <p id="c_costos">Todos los Centros de Costos</p>
            <p id="us_doll">VALORES EXPRESADOS EN US DOLARES</p>
            <p id="naturaleza">ACTIVOS Y GASTOS</p>


        </div> -->

        <div class="header" >
            <p id="logo"><img src="{{$empresa->logo}}" /></p>
            <p id="empresa_nombre">{{$empresa->nombre}}</p>
            <h2 id="titulo_balance">Balance de Comprobación</h2>
            <p id="periodo">Periodo: {{$month_name}} - {{$year}}</p>
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
    </div>
</section>

</body>
</html>
