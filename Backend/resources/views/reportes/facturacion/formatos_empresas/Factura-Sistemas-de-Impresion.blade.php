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

        #cliente    {top: 4.6cm; left: 3.2cm; }
        #direccion  {top: 5.2cm; left: 4cm; }
        #fecha      {top: 4.5cm; left: 17cm; }
        #nit        {top: 5.8cm; left: 17.2cm; }

        #tabla{position: absolute; top: 7.5cm; margin-left: 1.7cm;  width: 19cm;}
        
        #cantidad{width: 1.4cm; }
        #descripcion{width: 9cm; }
        #pre-uni{width: 2cm; }
        #sujetas{width: 1.6cm; }
        #exenta{width: 1.6cm; }
        #grab{width: 2.5cm; }

        #letras {top: 23.5cm; left: 3cm; width: 10cm; word-break: break-all; white-space: normal; font-size: 12px; }

        #subtotal{top: 23.5cm; left: 18cm; width: 2cm; text-align: right; }
        #total{top: 25.5cm; left: 18cm; width: 2cm; text-align: right; }
        .text-right{
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="container_fluid" id="body">
        <div id="header">
            <p id="cliente">{{$venta->nombre_cliente}}</p>
            @if ($venta->id_cliente)
                <p id="direccion">{{$cliente->empresa_direccion ?? $cliente->direccion}}</p>
            @endif
            <!-- <p id="condicion">{{$venta->estado == 'Pendiente' ? 'Crédito' : 'Contado'}}</p> -->
            <p id="fecha">{{Carbon\Carbon::parse($venta->fecha)->format('d/m/Y')}}</p>
            @if ($venta->id_cliente)
                <p id="nit">{{$cliente->nit ?? $cliente->dui}}</p>
            @endif
        </div>        
        <table id="tabla">
            <thead>
                <tr>
                    <th id="cantidad" scope="col"></th>
                    <th id="descripcion" scope="col"></th>
                    <th id="pre-uni" scope="col"></th>
                    <th id="sujetas" scope="col"></th>
                    <th id="exenta" scope="col"></th>
                    <th id="grab" scope="col"></th>
                </tr>
            </thead>
            <tbody>
                @php($iva = $venta->empresa()->pluck('iva')->first() / 100);

                @foreach($venta->detalles as $detalle)
                <tr>
                    <td>{{ $detalle->cantidad }}</td>
                    <td>{{ $detalle->nombre_producto }}</td>
                    <td class="text-right">${{ number_format($detalle->precio + (($venta->iva != 0) ? ($detalle->precio * $iva) : 0), 2) }}</td>
                    <td></td>
                    <td></td>
                    <td class="text-right">${{ number_format($detalle->total + (($venta->iva != 0)  ? ($detalle->total * $iva) : 0), 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div id="totales">
            <p id="letras"> {{$dolares}} DÓLARES CON {{$centavos}} CENTAVOS.</p>
            <p id="subtotal">${{number_format($venta->total,2)}}</p>
            <p id="total">${{number_format($venta->total,2)}}</p>
        </div> 

    </div>
</body>

</html>
