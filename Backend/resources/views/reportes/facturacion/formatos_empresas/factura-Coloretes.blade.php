<!DOCTYPE html>
<html>
<head>
    <title>Coloretes {{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 10px; margin: 0; padding: 0;}
        html, body {
            width: 21.59cm; height: 15.5cm;
            font-family: serif;
            line-height: 11px;
            margin-top: 0cm;
        }
        .factura{margin: 4cm 1cm 0cm 1cm; position: relative; height: 5.5cm;
/*            border: 1px solid red;*/
        }
        p{margin: 0px 0px 1px 0px; }

        table {text-align: left; border-collapse: collapse; width: 100%;}
        table th{border: 1px solid #000; text-align: center; line-height: 10px;}
        table td{height: 0.3cm; padding-left: 3px;}

        table .footer{position: absolute; bottom: 0px;}
        table.footer td{ border: 1px solid #000; line-height: 14px;}

        .cantidad{ width: 1.5cm; text-align: center;}
        .codigo{ width: 2cm; text-align: center;}
        .producto{ width: 8cm; text-align: left;}
        .precio{ width: 2cm; text-align: center;}
        .sujetas{ width: 1.5cm; text-align: center;}
        .exentas{ width: 1.5cm; text-align: center;}
        .gravadas{ width: 2cm; text-align: right; padding-right: 5px;}
        .text-right{text-align: right;}
        
        .no-print{position: absolute;}

    </style>
    
    <style media="print"> .no-print{display: none; } </style>

</head>
<body>
<body>

    
    <section class="factura" style="margin-bottom: 8cm;">
        <div id="header">
            <p><b>Fecha:</b> {{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
            <p><b>Cliente:</b> {{ $venta->nombre_cliente }}</p>
            @if ($venta->cliente_id)
                <p><b>DUI/NIT:</b> {{ $cliente->nit }}</p>
                <p><b>Dirección:</b> {{ $cliente->departamento }} &nbsp; {{ $cliente->direccion }} 
            @endif
            <p><b>Cond. Operación:</b> {{ $venta->forma_pago }} @if ($venta->detalle_banco) Banco: {{$venta->detalle_banco}} @endif </p>
        </div>
                    
        <table>
            <thead>
                <tr>
                    <th>Cant.</th>
                    <th>Cod.</th>
                    <th>Descripción</th>
                    <th>Precio <br> Unitario</th>
                    <th>Ventas No<br> Sujetas</th>
                    <th>Ventas <br> Exentas</th>
                    <th>Ventas <br> Afectas</th>
                </tr>
            </thead>
            <tbody>
                @php($iva = $venta->empresa()->pluck('iva')->first() / 100)
                @foreach($venta->detalles->take(10) as $detalle)
                <tr>
                    <td class="cantidad">   {{ $detalle->cantidad }}</td>
                    <td class="codigo">   {{ $detalle->producto()->pluck('codigo')->first() }}</td>
                    <td class="producto">   {{ $detalle->nombre_producto  }}</td>
                    <td class="precio">     ${{ number_format($detalle->precio + (($venta->iva != 0) ? ($detalle->precio * $iva) : 0), 2) }}</td>
                    <td class="sujetas">   </td>
                    <td class="exentas">    </td>
                    <td class="gravadas">  ${{ number_format($detalle->total + (($venta->iva != 0)  ? ($detalle->total * $iva) : 0), 2) }} </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <br>
        <table class="footer">
            <tbody>
                <tr>
                    <td colspan="2">
                        Son: {{$dolares}} DÓLARES CON {{$centavos}} CENTAVOS.
                    </td>
                    <td rowspan="4" style="padding-left: 15px;" width="200px">
                        Sumas <br>
                        Ventas Exentas <br>
                        Ventas No Sujetas <br>
                        Sub-Total <br>
                        (-) IVA Retenido <br>
                        Venta Total
                    </td>
                    <td rowspan="4" class="text-right"  width="60px" style="padding-right: 5px;">
                        $ {{ number_format($venta->total, 2) }} <br>
                        <br>
                        <br>
                        <br>
                        <br>
                        <b>$ {{ number_format($venta->total, 2) }}</b>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" class="text-center">Operaciones mayores a $200:</td>
                </tr>
                <tr>
                    <td>Entrega/Nombre/DUI </td>
                    <td>Recibe/Nombre/DUI </td>
                </tr>
                <tr>
                    <td></td>
                    <td></td>
                </tr>
            </tbody>
        </table>

    </section>

    <section class="factura">
        <div id="header">
            <p><b>Fecha:</b> {{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
            <p><b>Cliente:</b> {{ $venta->nombre_cliente }}</p>
            @if ($venta->cliente_id)
                <p><b>DUI/NIT:</b> {{ $cliente->nit }}</p>
                <p><b>Dirección:</b> {{ $cliente->departamento }} &nbsp; {{ $cliente->direccion }} 
            @endif
            <p><b>Cond. Operación:</b> {{ $venta->forma_pago }} @if ($venta->detalle_banco) Banco: {{$venta->detalle_banco}} @endif </p>
        </div>
                            
        <table>
            <thead>
                <tr>
                    <th>Cant.</th>
                    <th>Cod.</th>
                    <th>Descripción</th>
                    <th>Precio <br> Unitario</th>
                    <th>Ventas No<br> Sujetas</th>
                    <th>Ventas <br> Exentas</th>
                    <th>Ventas <br> Afectas</th>
                </tr>
            </thead>
        <tbody>
            @php($iva = $venta->empresa()->pluck('iva')->first() / 100)
            @foreach($venta->detalles->take(10) as $detalle)
            <tr>
                <td class="cantidad">   {{ $detalle->cantidad }}</td>
                <td class="codigo">   {{ $detalle->producto()->pluck('codigo')->first() }}</td>
                <td class="producto">   {{ $detalle->nombre_producto  }}</td>
                <td class="precio">     ${{ number_format($detalle->precio + (($venta->iva != 0) ? ($detalle->precio * $iva) : 0), 2) }}</td>
                <td class="sujetas">   </td>
                <td class="exentas">    </td>
                <td class="gravadas">  ${{ number_format($detalle->total + (($venta->iva != 0)  ? ($detalle->total * $iva) : 0), 2) }} </td>
            </tr>
            @endforeach
        </tbody>
        </table>
        <br>
        <table class="footer">
            <tr>
                <td colspan="2">
                    Son: {{$dolares}} DÓLARES CON {{$centavos}} CENTAVOS.
                </td>
                <td rowspan="4" style="padding-left: 15px;" width="200px">
                    Sumas <br>
                    Ventas Exentas <br>
                    Ventas No Sujetas <br>
                    Sub-Total <br>
                    (-) IVA Retenido <br>
                    Venta Total
                </td>
                <td rowspan="4" class="text-right"  width="60px" style="padding-right: 5px;">
                    $ {{ number_format($venta->total, 2) }} <br>
                    <br>
                    <br>
                    <br>
                    <br>
                    <b>$ {{ number_format($venta->total, 2) }}</b>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="text-center">Operaciones mayores a $200:</td>
            </tr>
            <tr>
                <td>Entrega/Nombre/DUI </td>
                <td>Recibe/Nombre/DUI </td>
            </tr>
            <tr>
                <td></td>
                <td></td>
            </tr>
        </table>

    </section>

</body>
</html>
