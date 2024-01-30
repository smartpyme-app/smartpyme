<html>

<head>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <title>Factura Via del Mar {{$venta->documento}} - {{$venta->correlativo}}</title>
    <style>
        
        * {
            font-size: 10px;
            margin: 0px;
        }
        html {
            height: 13.97cm;
            width: 10.795cm;
        }
        table, tr, th, td {
            font-size: 10px;
        }
        tr, td, th{
             border-style : hidden!important;
             font-size: 10px;
        }


        #header p, #totales p{
            position: absolute;
        }
        #cliente{
            top: 3.5cm;
            left: 1.8cm;
        }
        #direccion{
            top: 3.8cm;
            left: 1.8cm;
        }
        #nit{
            top: 4.7cm;
            left: 1.8cm;
        }
        #condicion{
            top: 4.2cm;
            left: 2cm;
        }
        #fecha{
            top: 4.2cm;
            left: 7.8cm;
        }
        #tabla{
            position: absolute;
            top: 5.5cm;
            margin-left: 1cm;
        }
        #fecha{
            margin-right: 2.0cm;
        }
        #cantidad{
            width: 0.3cm;
        }
        #descripcion{
            width: 4.5cm;
        }
        #pre-uni{
            width: 1.1cm;
        }
        #sujetas{
            width: 0.6cm;
        }
        #exenta{
            width: 0.6cm;
        }
        #grab{
           width: 1.3cm;
        }
        #table{
            height: 6.5cm;
            width: 7.6cm;
            margin-left: 0.55cm;
        }
        #subtotal{
            top: 10.3cm;
            left: 9.3cm;
            text-align: right;
        }
        #total{
            top: 12.7cm;
            left: 9.3cm;
            text-align: right;
        }
        #letras {
            top: 10.3cm; left: 1.5cm;
            width: 5cm; word-break: break-all; white-space: normal;
        }
        .text-right{
            text-align: right;
        }
    </style>
</head>
<body style="position: relative; margin-left: 5cm;">
    <div class="container_fluid" id="body">
        <div id="header">
            <p id="cliente">{{$venta->cliente}}</p>
            <p id="direccion">{{$cliente->direccion}}</p>
            <p id="condicion">{{$venta->estado == 'Pendiente' ? 'Crédito' : 'Contado'}}</p>
            <p id="fecha">{{Carbon\Carbon::parse($venta->fecha)->format('d/m/Y')}}</p>
            <p id="nit">{{$cliente->nit}}</p>
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
                    <td>{{ $detalle->producto }}</td>
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
