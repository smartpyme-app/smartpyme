<!DOCTYPE html>
<html>
<head>
    <title>Kiero {{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 15px; margin: 0; padding: 0;}
        html, body {
            width: 10cm; height: 27cm;
            font-family: sans-serif;
            line-height: 15px;
            margin-top: 0cm;
        }
        .factura{margin: 0.5cm; position: relative; height: 12cm;}
        p{margin: 0px 0px 4px 0px; }

        .head{
/*            margin-top: 4cm;*/
        }

        table   {text-align: left; border-collapse: collapse; width: 100%;}
        table th{border: 0px solid #000; text-align: left; line-height: 15px; padding-right: 10px;  font-size: 14px !important;}
        table td{height: 0.5cm; padding-right: 10px;  font-size: 14px !important;}

        table#footer td{font-size: 15px !important;}
        table#footer{position: absolute; bottom: 50px;}
/*        table#footer td{ border: 1px solid #000; line-height: 15px;}*/

        .cantidad{ width: 0.7cm; text-align: left;}
        .producto{ width: 3.8cm; text-align: left;}
        .precio{ width: 1.3cm; text-align: left;}
        .gravadas{ width: 1.3cm; text-align: left;}
        .letras{ margin-top: 120px; position: absolute; bottom: -400px;}

        .no-print{position: absolute;}
        .text-right{text-align: right;}

    </style>

    <style media="print"> .no-print{display: none; } </style>

</head>
<body>
<body>


    <section class="factura">
        <div id="header"class="head" style="margin-bottom: 20px;">
            <p><b>Cliente:</b> {{ $venta->nombre_cliente }}</p>
            @if ($venta->id_cliente)
                @if ($cliente->ncr)
                    <p><b>NCR:</b> {{ $cliente->ncr }}</p>
                @endif
                @if ($cliente->nit)
                    <p><b>NIT:</b> {{ $cliente->nit }}</p>
                @endif
                    <p><b>Dirección:</b> {{ $cliente->municipio }} &nbsp;{{ $cliente->departamento }} &nbsp; {{ $cliente->direccion_empresa ? $cliente->direccion_empresa : $cliente->direccion }}
                @if ($cliente->giro)
                    <p><b>Giro:</b> {{ $cliente->giro }}</p>
                @endif
                @if ($cliente->dui)
                    <p><b>DUI:</b> {{ $cliente->dui }}</p>
                @endif
            @endif
            <p><b>Fecha:</b> {{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
            {{-- <p><b>Pago:</b> {{ $venta->forma_pago }} @if ($venta->detalle_banco) <b>Banco:</b> {{$venta->detalle_banco}} @endif </p> --}}
            <p><b>Vendedor:</b> {{ $venta->nombre_usuario }}</p>

        </div>

        @php($iva = $venta->empresa()->pluck('iva')->first() / 100)

        <table>
            <thead>
                <tr>
                    <th>CANT.</th>
                    <th>DESCRIPCIÓN</th>
                    <th>P. UNI</th>
                    <th>TOTAL</th>
                </tr>
            </thead>
        <tbody>
            @foreach($venta->detalles->take(18) as $detalle)
            <tr>
                <td class="cantidad">   {{ $detalle->cantidad }}</td>
                <td class="producto">   {{ $detalle->producto()->pluck('barcode')->first() }} - {{ $detalle->nombre_producto  }}</td>
                <td class="precio">     ${{ number_format($detalle->precio + (($venta->iva != 0) ? ($detalle->precio * $iva) : 0), 2) }}</td>
                <td class="gravadas">  ${{ number_format($detalle->total + (($venta->iva != 0)  ? ($detalle->total * $iva) : 0), 2) }} </th>
            </tr>
            @endforeach
        </tbody>
        </table>

        <table id="footer" style="margin-bottom: 230px; width: 100%;">
            <tr>
                <td class="text-right" width="55%"><b>Venta Total</b></td>
                <td><b>$ {{ number_format($venta->total, 2) }}</b></td>
            </tr>
            <tr>
                <td colspan="2" style="text-align: center;"> <br> {{$dolares}} DÓLARES CON {{$centavos}} CENTAVOS.</td>
            </tr>
        </table>

    </section>

</body>
</html>
