<html>

<head>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <title>{{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
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
            margin-top: 3.0cm;
            margin-right: 0.5cm;
        }
        #head-customer{
            margin-top: 4.5cm;
        }
        #cliente{
            margin-left: 2.4cm;
        }
        #fecha{
            margin-top: 0.0cm;
            margin-right: 7.5cm;
        }
        #cantidad{
            width: 1.3cm;
        }
        #descripcion{
            width: 8.0cm;
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
            height: 9.4cm;
            width: 14.2cm;
        }
        #total{
            margin-left: 8.4cm;
            padding-left: 0.7cm;
        }
        #numeros{
          margin-left: 1.6cm;
          margin-top: 0.4cm;
        }
        #totales{
            margin-right: 1.8cm;
            padding-right: 0.1cm;
            width: 73%;
            padding-top: 0.3cm;
           
        }
        #p-dep{
            padding-left: 0.7cm;
        }
        #p-estado{
            padding-left: 1.6cm;
        }
        #p-dirr{
            padding-top: 0.1cm;
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
            padding-top: 0.15cm;
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
                <p id="p-dirr">{{$cliente->nit}}</p>
                
            </div>
            <div class="float-right no-margin" id="fecha">
                <p class="">{{Carbon\Carbon::parse($venta->fecha)->format('d/m/Y')}}</p>

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
                        @php($iva = $venta->empresa()->pluck('iva')->first() / 100);

                        @foreach($venta->detalles as $detalle)
                        <tr>
                            <td class="text-right mr-3">{{ $detalle->cantidad }}</td>
                            
                            <td class="pl-5">&nbsp;&nbsp;{{ $detalle->descripcion }}</td>
                            <td class="text-center ml-2">&nbsp;&nbsp;&nbsp;&nbsp;{{\Currency::currency($venta->empresa()->pluck('moneda')->first())->format($detalle->precio + (($venta->iva != 0) ? ($detalle->precio * $iva) : 0))}}</td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{\Currency::currency($venta->empresa()->pluck('moneda')->first())->format($detalle->total + (($venta->iva != 0) ? ($detalle->total * $iva) : 0))}}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            <div class="d-flex d-block" id="totales">
                <div class="float-left" id="numeros">
                    <p>{{$dolares}} DÓLARES CON {{$centavos}} CENTAVOS.</p>
                </div>
                <div class="float-right" id="total">
                    <p class="pb-0 ">{{\Currency::currency($venta->empresa()->pluck('moneda')->first())->format(($venta->total))}}</p>
                    
                    <br><br><br>
                    @if($venta->impuesto)
                    <p class="" id="t-imp">${{$venta->impuesto}}</p>
                    @endif
                    <p class="" id="t-total">{{\Currency::currency($venta->empresa()->pluck('moneda')->first())->format(($venta->total))}}</p>
                </div>
            </div> 
        </div>

    </div>
</body>

</html>
