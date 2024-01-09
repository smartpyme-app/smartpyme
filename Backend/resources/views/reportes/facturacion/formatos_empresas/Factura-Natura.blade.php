<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Natura {{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 14px; margin: 0; padding: 0;}
        html, body {
            width: 21.59cm; height: 27.94;
            font-family: serif;
            line-height: 11px;
            margin-top: 0cm;
        }
        .factura{margin: 2cm 2cm 2cm 2cm; position: relative; height: 27cm;}
        p{margin: 0px 0px 5px 0px; }

        table   {text-align: left; border-collapse: collapse; width: 100%;}
        table th{border: 1px solid #000; text-align: left; line-height: 14px; padding: 5px;}
        table td{border: 1px solid #000; text-align: left; line-height: 14px; padding: 5px;}

        table#footer{position: absolute; bottom: 0px; width: 100%;}

        table#footer td{ border: 1px solid #000; line-height: 14px;}

        .cantidad{ width: 1cm; text-align: left;}
        .producto{ width: 6cm; text-align: left !important;}
        .precio{ width: 2cm; text-align: left;}
        .gravadas{ width: 2cm; text-align: left; padding-right: 5px;}
        
        .no-print{position: absolute;}

    </style>
    
    <style media="print"> .no-print{display: none; } </style>

</head>
<body>
<body>

    
    <section class="factura">
        <div id="header">
            <table>
                <tr>
                    <td style="border: 0px; text-align: left;">
                        @if ($empresa->logo)
                            <img src="{{asset($empresa->logo)}}" alt="Logo" height="60px;">
                        @endif
                    </td>
                    <td style="border: 0px; text-align: right;">
                        <p style="padding: 15px; border: 1px solid #900; display: inline-block;">FACTURA {{ $venta->correlativo }}</p>
                    </td>
                </tr>
                <tr>
                    <td style="border: 0px;width: 70%;">
                        <p><b>CLIENTE:</b> {{ $venta->nombre_cliente }}</p>
                    </td>
                    <td style="border: 0px; width: 30%;">
                        <p><b>FECHA:</b> {{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
                        <p><b>VENDEDOR:</b> {{ $venta->nombre_usuario }}</p>
                    </td>
                </tr>
            </table>
        </div>
        <br>  
        @php($iva = $venta->empresa()->pluck('iva')->first() / 100)
        
        <table>
            <thead>
                <tr>
                    <th>CANTIDAD</th>
                    <th>PRODUCTO</th>
                    <th>PRECIO UNITARIO</th>
                    <th>TOTAL</th>
                </tr>
            </thead>
        <tbody>
            @foreach($venta->detalles as $detalle)
            <tr>
                <td class="cantidad">   {{ $detalle->cantidad }}</td>
                <td class="producto">   {{ $detalle->nombre_producto  }}</td>
                <td class="precio">     ${{ number_format($detalle->precio + (($venta->iva != 0) ? ($detalle->precio * $iva) : 0), 2) }}</td>
                <td class="gravadas">  ${{ number_format($detalle->total + (($venta->iva != 0)  ? ($detalle->total * $iva) : 0), 2) }} </th>
            </tr>
            @endforeach
        </tbody>
        </table>

        <table id="footer">
            <tr>
                <td colspan="2" style="padding: 15px;">
                    Son: {{$dolares}} DÓLARES CON {{$centavos}} CENTAVOS.
                </td>
                <td class="text-right"  width="100px" style="padding: 15px;">   
                    <b>$ {{ number_format($venta->total, 2) }}</b>
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <p>***PORFAVOR REVISAR LA MERCADERIA, UNA VEZ DESPACHADA YA NO SE ACEPTAN RECLAMOS</p>
                </td>
            </tr>
        </table>

    </section>

</body>
</html>
