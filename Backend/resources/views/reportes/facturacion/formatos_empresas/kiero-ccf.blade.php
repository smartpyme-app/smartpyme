<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Kiero {{$venta->documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 15px; margin: 0; padding: 0;}
        html, body {
            width: 10cm; height: 27cm;
            font-family: sans-serif;
            line-height: 15px;
            margin-top: -0.6cm;
        }
        .factura{margin: 2cm 0.5cm 0cm 0.5cm; position: relative; height: 13cm;}
        p{margin: 0px 0px 4px 0px; }

        table   {text-align: left; border-collapse: collapse; width: 100%;}
        table th{border: 0px solid #000; text-align: left; line-height: 15px; padding-right: 10px;  font-size: 14px !important;}
        table td{height: 0.5cm; padding-right: 10px;  font-size: 14px !important;}

        table#footer td{font-size: 15px !important;}
        table#footer{position: absolute; bottom: 0px;}
/*        table#footer td{ border: 1px solid #000; line-height: 15px;}*/

        .cantidad{ width: 0.7cm; text-align: left;}
        .producto{ width: 4cm; text-align: left;}
        .precio{ width: 1.2cm; text-align: left;}
        .gravadas{ width: 1.2cm; text-align: left;}
        .letras{ margin-top: 20px; position: absolute; bottom: -300px;}
        
        .no-print{position: absolute;}
        .text-right{text-align: right;}

    </style>
    
    <style media="print"> .no-print{display: none; } </style>

</head>
<body>
<body>

    
    <section class="factura">
        <div id="header" style="margin-bottom: 20px;">
            <p><b>Cliente:</b> {{ $venta->cliente }}</p>
            @if ($cliente->ncr)
            <p><b>NCR:</b> {{ $cliente->ncr }}</p>
            @endif
            @if ($cliente->nit)
            <p><b>NIT:</b> {{ $cliente->nit }}</p>
            @endif
            <p><b>Dirección:</b> {{ $cliente->municipio }} &nbsp;{{ $cliente->departamento }} &nbsp; {{ $cliente->direccion }} 
            @if ($cliente->giro)
            <p><b>Giro:</b> {{ $cliente->giro }}</p>
            @endif
            @if ($cliente->dui)
            <p><b>DUI:</b> {{ $cliente->dui }}</p>
            @endif
            <p><b>Fecha:</b> {{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
            <p><b>Pago:</b> {{ $venta->forma_pago }} @if ($venta->detalle_banco) <b>Banco:</b> {{$venta->detalle_banco}} @endif </p>
            <p><b>Vendedor:</b> {{ $venta->usuario }}</p>

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
                <td class="producto">   {{ $detalle->producto()->pluck('barcode')->first() }} - {{ $detalle->producto  }}</td>
                <td class="precio">     ${{ number_format($detalle->precio, 2) }}</td>
                <td class="gravadas">  ${{ number_format($detalle->total, 2) }} </th>
            </tr>
            @endforeach
        </tbody>
        </table>

        <table id="footer" style="margin-top: 120px; width: 100%;">
            <tr>
                <td class="text-right" width="50%">Sumas</td>
                <td>$ {{ number_format($venta->sub_total, 2) }}</td>
            </tr>
            <tr>
                <td class="text-right" width="50%">13% IVA</td>
                <td>$ {{ number_format($venta->iva, 2) }}</td>
            </tr>
            <tr>
                <td class="text-right" width="50%">Sub Total</td>
                <td>$ {{ number_format($venta->sub_total, 2) }}</td>
            </tr>
            <tr>
                <td class="text-right" width="50%">IVA Retenido</td>
                <td>$ {{ number_format($venta->retenido, 2) }}</td>
            </tr>
            <tr>
                <td class="text-right" width="50%">Venta no Sujeta</td>
                <td>$ {{ number_format($venta->no_sujeta, 2) }}</td>
            </tr>
            <tr>
                <td class="text-right" width="50%">Venta Exenta</td>
                <td>$ {{ number_format($venta->exenta, 2) }}</td>
            </tr>
            <tr>
                <td class="text-right" width="50%"><b>Venta Total</b></td>
                <td><b>$ {{ number_format($venta->total, 2) }}</b></td>
            </tr>
        </table>

        <p class="letras" style="text-align: center; width: 100%;">{{$dolares}} DÓLARES CON {{$centavos}} CENTAVOS.</p>
    </section>

</body>
</html>
