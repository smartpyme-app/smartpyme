<html>

<head>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <title>{{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
    <style>
        
        p {
            font-size: 12px;
        }
        html { margin: 0px}
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
            margin-top: 2.0cm;
            margin-right: 0.5cm;
        }
        #head-customer{
            margin-top: 4.5cm;
        }
        #cliente{
            margin-left: 1.4cm;
            width: 6.5cm;
        }
        #fecha{
            margin-right: 1.0cm;
        }
        #cantidad{
            width: 0.9cm;
        }
        #descripcion{
            width: 5.6cm;
        }
        #pre-uni{
            width: 1.1cm;
        }
        #sujetas{
            width: 1.0cm;
        }
        #exenta{
            width: 1.2cm;
        }
        #grab{
            width: 1.2cm;
        }
        #table{
            margin-top: 1cm;
            height: 7.6cm;
            width: 11.6cm;
            margin-left: 0.15cm;
        }
        #total{
            margin-left: 10cm;
        }
    </style>
</head>
<body>
    <div class="container_fluid">
        <div class="d-flex d-block" id="head-customer">
            <div class="float-left no-margin" id="cliente">
                <p class="">{{$venta->cliente}}</p>
                <p class="">{{$cliente->direccion}}</p>
                <p class="">{{$cliente->departamento}}</p>
                <p class="">{{$cliente->nit}}</p>
            </div>
            <div class="float-right no-margin" id="fecha">
                <p class="">{{Carbon\Carbon::parse($venta->fecha)->format('d/m/Y')}}</p>
                <p class="">{{$cliente->ncr}}</p>
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
                        @php($total = 0);

                        @foreach($venta->detalles as $detalle)
                        <tr>
                            <td class="text-center">{{ $detalle->cantidad }}</td>
                            <td class="text-center">{{ $detalle->nombre_producto }}</td>
                            <td class="text-center">${{ $detalle->precio }}</td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">${{ $detalle->total}}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            <div class="d-flex d-block" id="totales">
                <div class="float-left no-margin" id="cliente">
                    <br>
                    {{-- <p>{{$total_text}}</p> --}}
                </div>
                <div class="float-right no-margin" id="total">
                    <p class="">${{number_format($venta->sub_total,2)}}</p>
                    <p class="">${{number_format($venta->iva,2)}}</p>
                    <p class="">${{number_format($venta->sub_total,2)}}</p>
                    <br>
                    <p class="">${{number_format($venta->total,2)}}</p>
                    
                </div>
            </div> 
        </div>

    </div>
</body>

</html>
