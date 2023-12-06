<html>

<head>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <title>{{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
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
            margin-top: 3.4cm;
            margin-right: 0.5cm;
        }
        #head-customer{
            margin-top: 3.2cm;
        }
        #cliente{
            margin-left: 2.6cm;
        }
        #fecha{
            margin-top: 1.0cm;
            margin-right: 9.9cm;
        }
        #fe{
            margin-right: 2.5cm;
            margin-top: 1.6cm;
        }
        #cantidad{
            width: 1.3cm;
        }
        #descripcion{
            width: 10.0cm;
            margin-left: 2.5cm;
        }
        #pre-uni{
            width: 1.9cm;
        }
        #sujetas{
            width: 1.5cm;
        }
        #exenta{
            width: 1.5cm;
        }
        #grab{
            width: 2.0cm;
        }
        #table{
            height: 4.6cm;
            width: 19.6cm;
            margin-left: 0.55cm;
        }
        #total{
            margin-left: 11cm;
        }
        #numeros{
          margin-left: 1.6cm;
          margin-top: 0.4cm;
        }
        #totales{
            margin-right: 0.8cm;
        }
    </style>
</head>
<body class="border" style="border-style: solid 1px;">
    <div class="container_fluid" id="body">
        <div class="d-flex d-block" id="head-customer">
            <div class="float-left no-margin" id="cliente">
                <p class="">{{$cliente->nombre}} {{$cliente->apellido}}</p>
                <p class="">&nbsp;&nbsp;{{$cliente->direccion}}</p>
                <p class="">&nbsp;&nbsp;&nbsp;{{$cliente->departamento}}</p>
                <p class="">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{$cliente->giro}}</p>
                <p class="">&nbsp;&nbsp;{{$cliente->ncr}}</p>
                @if($venta->estado == 'Pagada')
                    <p class="">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;CONTADO</p>
                @else
                    <p class="">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; CRÉDITO</p>
                @endif
            </div>
            <div class="float-right no-margin" id="fecha">
                <br>
                <p class="">{{$cliente->nit}}</p>
                
            </div>
            <div class="float-right no-margin" id="fe">
                <br>
               <p class="" id="fe">{{Carbon\Carbon::parse($venta->fecha)->format('d/m/Y')}}</p>
            </div>
        </div>        
        <div class="d-flex justify-content-center " id="detalle">
                <table class="table-bordered" id="table">
                    <thead>
                        <tr>
                            <th class="mr-3" id="cantidad" scope="col"></th>
                            <th class="ml-5" id="descripcion" scope="col"></th>
                            <th class="text-center" id="pre-uni" scope="col"></th>
                            <th class="text-center" id="pre-uni" scope="col"></th>
                            <th class="text-center" id="sujetas" scope="col"></th>
                            <th class="text-center" id="exenta" scope="col"></th>
                            <th class="text-center" id="grab" scope="col"></th>
                        </tr>
                    </thead>
                    <tbody class="">
                        @php($total = 0);

                        @foreach($venta->detalles as $detalle)
                        <tr>
                            <td class="text-right mr-3">{{ $detalle->cantidad }}</td>
                            <td class="pl-5">{{ $detalle->nombre_producto }}</td>
                            <td class="text-center">{{ $detalle->codigo }}</td>
                            <td class="text-center ml-2">{{\Currency::currency("USD")->format(($detalle->precio))}}</td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{\Currency::currency("USD")->format(($detalle->total))}}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            <div class="d-flex d-block" id="totales">
                <div class="float-left no-margin" id="numeros">
                    <p>{{$dolares}} DÓLARES CON {{$centavos}} CENTAVOS.</p>
                </div>
                <div class="float-right no-margin" id="total">
                    @if($venta->iva > 0)
                    <p class="">{{\Currency::currency("USD")->format(($venta->sub_total))}}</p>
                    @else
                    <p class="">{{\Currency::currency("USD")->format(($venta->sub_total)/1.13)}}</p>
                    @endif

                    @if($venta->iva > 0)
                    <p class="">{{\Currency::currency("USD")->format(($venta->iva))}}</p>
                    @else
                    <p class="">{{\Currency::currency("USD")->format(($venta->sub_total / 1.13) * 0.13)}}</p>
                    @endif

                    <br><br>
                    <p class="mt-1">{{\Currency::currency("USD")->format(($venta->total))}}</p>
                </div>
            </div> 
        </div>

    </div>
</body>

</html>
