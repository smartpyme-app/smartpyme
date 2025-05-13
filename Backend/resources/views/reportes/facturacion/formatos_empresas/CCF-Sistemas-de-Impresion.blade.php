<html>

<head>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <title>Factura Sistema de Impresión {{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
    <style>
        
        * {
            font-size: 14px;
            margin: 0px;
        }
        html {
            height: 28cm;
            width: 21.5cm;
        }

        #header p, #totales p{ position: absolute; }

        #cliente    {top: 5.2cm; left: 3.2cm; }
        #direccion  {top: 5.8cm; left: 4cm; }
        #departamento  {top: 7cm; left: 4cm; }
        #condicion  {top: 7.7cm; left: 4cm; }

        #fecha      {top: 5.2cm; left: 14cm; }
        #nrc        {top: 5.8cm; left: 14.8cm; }
        #nit        {top: 6.5cm; left: 14cm; }
        #giro        {top: 7.1cm; left: 14cm; }

        #tabla{position: absolute; top: 10cm; margin-left: 1.7cm;  width: 19.3cm;}
        
        .cantidad{width: 1.7cm; }
        .producto{width: 9.7cm; }
        .precio{width: 1.6cm; }
        .sujetas{width: 2cm; }
        .exentas{width: 1.6cm; }
        .gravadas{width: 2.8cm; }

        #letras {top: 22.5cm; left: 3cm; width: 10cm; word-break: break-all; white-space: normal; font-size: 12px; }

        #suma{top: 22cm; left: 18cm; width: 2cm; text-align: right; }
        #iva{top: 22.6cm; left: 18cm; width: 2cm; text-align: right; }
        #sub_total{top: 23.3cm; left: 18cm; width: 2cm; text-align: right; }
        #total{top: 25.5cm; left: 18cm; width: 2cm; text-align: right; }
        .text-right{
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="container_fluid" id="body">
        <div id="header">
            <p id="fecha">{{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
            <p id="cliente">{{ $venta->nombre_cliente }}</p>
            <p id="direccion">{{ $cliente->empresa_direccion ?? $cliente->direccion }}</p>
            <p id="departamento">{{ $cliente->departamento }}</p>
            <p id="nit">{{ $cliente->nit }}</p>
            <p id="nrc">{{ $cliente->ncr }}</p>
            <p id="giro">{{ \Illuminate\Support\Str::limit($cliente->giro, 40, $end = '...') }}</p>
            <p id="condicion">{{ $venta->condicion }}</p>
            <p id="nit">{{ $cliente->nit }}</p>
        </div>      
        <table id="tabla">
            <tbody>
                @foreach($venta->detalles as $detalle)
                <tr>
                    <td class="cantidad">   {{ number_format($detalle->cantidad, 0) }}</td>
                    <td class="producto">   {{ $detalle->nombre_producto  }}</td>
                    <td class="precio">     ${{ number_format($detalle->precio, 2) }}</td>
                    <td class="sujetas">   </td>
                    <td class="exentas">    </td>
                    <td class="gravadas">  ${{ number_format($detalle->total, 2) }} </td> 
                </tr>
                @endforeach
            </tbody>
        </table>

        <div id="totales">
            <p id="letras"> {{$dolares}} DÓLARES CON {{$centavos}} CENTAVOS.</p>

            <p id="suma"> $ {{ number_format($venta->sub_total, 2) }}</p>
            <p id="iva"> $ {{ number_format($venta->iva, 2) }}</p>
            <p id="sub_total"> $ {{ number_format($venta->sub_total + $venta->iva, 2) }}</p>
            @if($venta->iva_retenido > 0)
                <p id="iva_retenido"> $ {{ number_format($venta->iva_retenido, 2) }}</p>
            @endif
            @if($venta->no_sujeta > 0)
                <p id="no_sujeta"> $ {{ number_format($venta->no_sujeta, 2) }}</p>
            @endif
            @if($venta->exenta > 0)
                <p id="exenta"> $ {{ number_format($venta->exenta, 2) }}</p>
            @endif
            @if($venta->cuenta_a_terceros > 0)
                <p id="terceros_letras"> Cobros por tramites a terceros</p>
                <p id="cuenta_a_terceros"> $ {{ number_format($venta->cuenta_a_terceros, 2) }}</p>
            @endif
            <p id="total"> <b>$ {{ number_format($venta->total, 2) }}</b></p>
        </div>

    </div>
</body>

</html>
