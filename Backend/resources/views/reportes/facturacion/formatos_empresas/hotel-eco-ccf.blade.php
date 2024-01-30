<html>

<head>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <title>{{$venta->documento}} - {{$venta->correlativo}}</title>
    <style>
        
        p {
            font-size: 11px;
        }
        html { margin: 0px;}
        table, tr, th, td {
            font-size: 12px;
        }
        tr, td, th{
             border-style : hidden !important;
             font-size: 11px;
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
            margin-top: 4.2cm;
        }
        #cliente{
            margin-left: 1.9cm;
        }
        #fecha{
            margin-top: 0cm;
            margin-right: 8.5cm;
        }
        #cantidad{
            width: 1.0cm;
        }
        #descripcion{
            width: 8.2cm;
            margin-left: 0cm;
        }
        #pre-uni{
            width: 1.4cm;
        }
        #sujetas{
            width: 1.4cm;
        }
        #exenta{
            width: 1.4cm;
        }
        #grab{
            width: 1.4cm;
        }
        #table{
            height: 7.8cm;
            width: 14.2cm;
        }
        #total{
            margin-left: 8.6cm;
        }
        #numeros{
          margin-left: 1.6cm;
          margin-top: 0.4cm;
          padding-top: 0.5cm;
        }
        #totales{
            margin-right: 1.8cm;
            padding-right: 0.3cm;
            width: 73%;
           
        }
        #p-dep{
            padding-left: 0.7cm;
        }
        #p-estado{
            padding-left: 1.6cm;
        }
        #p-dirr{
            padding-left: 0.5cm;
        }
        #p-dirre{
            padding-left: 0.7cm;
        }
        #p-nit{
            padding-top: 0.1cm;
        }
        #t-iva{
            margin-top: -0.3cm;
        }
        #t-total{
            padding-top: 0.1cm;
        }
        #t-imp{
            margin-bottom: 0.1cm;
        }
    </style>
</head>
<body class="border" style="border-style: solid 1px;">
    <div class="container_fluid" id="body">
        <div class="d-flex d-block" id="head-customer">
            <div class="float-left no-margin" id="cliente">
                <p id="p-cliente">{{$cliente->nombre}} {{$cliente->apellido}}</p>
                <p id="p-dirr">{{$cliente->direccion}}</p>
                <p id="p-dirre">{{$cliente->departamento}}</p>
                
            </div>
            <div class="float-right no-margin" id="fecha">
                <p class="">{{Carbon\Carbon::parse($venta->fecha)->format('d/m/Y')}}</p>
                <p id="p-dirr">{{$cliente->ncr}}</p>
                <p id="">{{$cliente->nit}}</p>
                <p id="">{{$cliente->giro}}</p>
            </div>
        </div>        
        <div class="d-flex justify-content-center " id="detalle">
                <table class="table-bordered" id="table">
                    <thead>
                        <tr>
                            <th class="mr-3" id="cantidad" scope="col"></th>
                            
                            <th class="ml-5" id="descripcion" scope="col"></th>
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
                            
                            <td class="pl-5">&nbsp;&nbsp;{{ $detalle->descripcion }}</td>
                            <td class="text-center ml-2">&nbsp;&nbsp;&nbsp;&nbsp;{{\Currency::currency(Auth::user()->moneda)->format(($detalle->precio))}}</td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{\Currency::currency(Auth::user()->moneda)->format(($detalle->total))}}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            <div class="d-flex d-block" id="totales">
                <div class="float-left" id="numeros">
                    <p>{{$dolares}} DÓLARES CON {{$centavos}} CENTAVOS.</p>
                </div>
                <div class="float-right pr-3" id="total">
                    <p class="pb-0 ">{{\Currency::currency(Auth::user()->moneda)->format(($venta->sub_total))}}</p>
                    <p id="t-iva" class="">{{\Currency::currency(Auth::user()->moneda)->format(($venta->iva))}}</p>
                    <br><br><br>
                    @if($venta->impuesto)
                    <p class="" id="t-imp">${{$venta->impuesto}}</p>
                    @endif
                    <p class="" id="t-total">{{\Currency::currency(Auth::user()->moneda)->format(($venta->total))}}</p>
                </div>
            </div> 
        </div>

    </div>
</body>

</html>
