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
            margin-top: 2.0cm;
            margin-right: 0.5cm;
        }
        #head-customer{
            margin-top: 3.2cm;
        }
        #cliente{
            margin-left: 1.4cm;
        }
        #fecha{
            margin-top: -0.3cm;
            margin-right: 3.0cm;
        }
        #cantidad{
            width: 2.3cm;
        }
        #descripcion{
            width: 8.0cm;
            margin-left: 2.5cm;
        }
        #pre-uni{
            width: 1.9cm;
        }
        #sujetas{
            width: 1.5cm;
        }
        #exenta{
            width: 2.2cm;
        }
        #grab{
            width: 2.5cm;
        }
        #table{
            height: 5.25cm;
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
        #p-dep{
            padding-left: 0.7cm;
        }
        #p-estado{
            padding-left: 2.5cm;
            padding-top: -0.1cm;
        }
        #p-saldo{
            padding-left: 3.7cm;
            padding-top: 0.3cm;
        }
    </style>
</head>
<body class="border" style="border-style: solid 1px;">
    <div class="container_fluid" id="body">
        <div class="d-flex d-block" id="head-customer">
            <div class="float-left no-margin" id="cliente">
                <p id="p-cliente">{{$venta->cliente}}</p>
                
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
                            <th class="text-center" id="pre-uni" scope="col"></th>
                            <th class="text-center" id="descripcion" scope="col"></th>
                            <th class="text-center" id="pre-uni" scope="col"></th>
                            <th class="text-center" id="sujetas" scope="col"></th>
                            <th class="text-center" id="exenta" scope="col"></th>
                            <th class="text-center" id="grab" scope="col"></th>
                        </tr>
                    </thead>
                    <tbody class="">
                        <tr>
                            <td class="text-right mr-3">{{ $venta->documento }} : {{ $venta->correlativo }}</td>
                            <td class=""></td>
                            <td class="text-center">{{ $recibo->concepto }}</td>
                            <td class="text-center ml-2"></td>
                            <td class="text-center"></td>
                            <td class="text-center"></td>
                            <td class="text-center">{{\Currency::currency(Auth::user()->moneda)->format(($recibo->total))}}</td>
                        </tr>
                    </tbody>
                </table>
            <div class="d-flex d-block" id="totales">
                <div class="float-left no-margin" id="numeros">
                    @if($venta->estado == 'Pagada')
                    <p id="p-estado">CONTADO</p>
                    @elseif($venta->estado == 'Pendiente')
                        <p id="p-estado">CREDITO</p>
                    @endif
                    <br>
                    <p id="p-saldo">{{\Currency::currency(Auth::user()->moneda)->format(($venta->total - $venta->recibos->sum('total')))}}</p>
                </div>
                <div class="float-right no-margin" id="total">
                    
                    <p class="mt-2">{{\Currency::currency(Auth::user()->moneda)->format(($recibo->total))}}</p>
                    <br>
                    <p class="mt-2">{{\Currency::currency(Auth::user()->moneda)->format(($recibo->total))}}</p>
                </div>
            </div> 
        </div>

    </div>
</body>

</html>
