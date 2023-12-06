<html>

<head>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <title>{{$venta->documento}} - {{$venta->correlativo}}</title>
    <style>
        
        p {
            font-size: 12px;
        }
        html { margin: 0px;}
        table, tr, th, td {
            font-size: 14px;
        }
        tr, td, th{
             border-style : hidden!important;
             font-size: 12px;
        }

        @page {
            margin: 10px 15px;
        }
        header {
            position: fixed;
            left: 0px;
            top: -160px;
            right: 0px;
            height: 100px;
            background-color: #ddd;
            text-align: center;
        }

        footer .page:after {
            content: counter(page);
        }

        .no-margin p {
            margin: 0 !important;
        }

        .img-logo {
            width: 70px;
        }

        #detalle{
            margin-top: 4.1cm;
            margin-right: 0.5cm;
        }
        #head-customer{
            margin-top: 4.35cm;
        }
        #cliente{
            margin-left: 1.8cm;
        }
        #fecha{
            margin-right: 2.0cm;
        }
        #cantidad{
            width: 0.3cm;
        }
        #descripcion{
            width: 5.5cm;
        }
        #pre-uni{
            width: 1.0cm;
        }
        #sujetas{
            width: 0.9cm;
        }
        #exenta{
            width: 1.0cm;
        }
        #grab{
            width: 1.0cm;
        }
        #table{
            height: 6.5cm;
            width: 7.6cm;
            margin-left: 0.55cm;
        }
        #total{
            margin-left: 10cm;
        }
        #body{
            
        }
        #totales{
            margin-right: 0.8cm;
        }
    </style>
</head>
<body class="">
    <div class="container_fluid" id="body">
        <div class="d-flex d-block" id="head-customer">
            <div class="float-left no-margin" id="cliente">
                <p class="">{{$venta->nombre_cliente}}</p>
                <p class="">{{$cliente->direccion}}</p>
            </div>
            <div class="float-right no-margin" id="fecha">
                <p class="">{{Carbon\Carbon::parse($venta->fecha)->format('d/m/Y')}}</p>
                <p class="">{{$cliente->nit}}</p>
            </div>
        </div>        
        <div class="d-flex justify-content-center" id="detalle">
                <table class="table table-sm border-0" id="table">
                    <thead>
                        <tr>
                            <th class="text-center" id="cantidad" scope="col"></th>
                            <th class="text-center" id="descripcion" scope="col"></th>
                            <th class="text-center" id="pre-uni" scope="col"></th>
                            <th class="text-center" id="sujetas" scope="col"></th>
                            <th class="text-center" id="exenta" scope="col"></th>
                            <th class="text-center" id="grab" scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php($iva = $empresa->iva / 100);

                        @foreach($venta->detalles as $detalle)
                        <tr>
                            <td class="text-center">{{ $detalle->cantidad }}</td>
                            <td class="text-center">{{ $detalle->descripcion }}</td>
                            <td class="text-center">${{ number_format($detalle->precio + (($venta->iva != 0) ? ($detalle->precio * $iva) : 0), 2) }}</td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">${{ number_format($detalle->total + (($venta->iva != 0)  ? ($detalle->total * $iva) : 0), 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            <div class="d-flex d-block" id="totales">
                <div class="float-left no-margin" id="cliente">
                    
                </div>
                <div class="float-right no-margin" id="total">
                    <p class="">${{number_format($venta->total,2)}}</p>
                    {{-- <p class="">${{number_format($venta->iva,2)}}</p> --}}
                    {{-- <p class="">${{number_format($venta->sub_total,2)}}</p> --}}
                    <br><br>
                    <p class="mt-1">${{number_format($venta->total,2)}}</p>
                </div>
            </div> 
        </div>

    </div>
</body>

</html>
