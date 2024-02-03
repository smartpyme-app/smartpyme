<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Recibo de abono #{{ $recibo->id }} - {{ $recibo->nombre_cliente }}</title>
    <style>

        *{ 
            margin: 0cm;
            font-family: 'system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue","Noto Sans","Liberation Sans",Arial,sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji"';
        }
        body {
            font-family: serif;
            margin: 50px;
        }
        h1,h2,h3,h4,h5,h6{
            color: #005CBB !important;
        }

        table{
            width: 100%;
            border-collapse: collapse;
        }
        .table th, .table td{
            border: 0px;
            border-collapse: collapse;
            padding: 10px 5px;
            text-align: left;
        }
        .text-right{
            text-align: right !important;
        }
        .border-bottom{
            border-bottom: 1px solid #005CBB !important;
        }


    </style>
    
</head>
<body>
{{-- <body onload="javascript:print();"> --}}

        <table>
            <tbody>
                <tr>
                    <td>
                        <h1>{{ $venta->empresa()->pluck('nombre')->first() }}</h1>
                        <p>
                            {{ $venta->empresa()->pluck('municipio')->first() }}
                            {{ $venta->empresa()->pluck('departamento')->first() }}
                        </p>
                        <p>{{ $venta->empresa()->pluck('direccion')->first() }}</p>
                        <p>{{ $venta->empresa()->pluck('telefono')->first() }}</p>
                    </td>
                    <td class="text-right">
                        @if ($venta->empresa()->pluck('logo')->first())
                            <img width="150" height="150" src="{{ asset('img/'.$venta->empresa()->pluck('logo')->first()) }}" alt="Logo">
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
        <br>
        <br>
        <h2 class="text-center">Comprobante de pago</h2>
        <br>
        <table>
            <tbody>
                <tr>
                    <td><h4>Cliente</h4></td>
                </tr>
                <tr>
                    <td>
                        <p>{{ $venta->nombre_cliente }}</p>
                        <p>
                            {{ $venta->cliente()->pluck('municipio')->first() }}
                            {{ $venta->cliente()->pluck('departamento')->first() }}
                            {{ $venta->cliente()->pluck('direccion')->first() }} <br>
                        </p>
                    </td>
                    <td>
                        <p>NCR:{{ $venta->cliente()->pluck('ncr')->first() }}</p>
                        <p>DUI:{{ $venta->cliente()->pluck('dui')->first() }}</p>
                        <p>Teléfono:{{ $venta->cliente()->pluck('telefono')->first() }}</p>
                    </td>
                    <td>
                        <p class="text-right">Abono #{{ $recibo->id }}</p>
                        <p class="text-right">Forma pago: {{$recibo->forma_pago}}</p>
                        <p class="text-right">Venta: {{$venta->nombre_documento}} #{{$venta->correlativo}}</p>
                        <p class="text-right">Fecha: {{ \Carbon\Carbon::parse($recibo->fecha)->format('d/m/Y') }}</p>
                    </td>
                </tr>
            </tbody>
        </table> 

        <br>

        <table class="table">
            <thead>
                <tr>
                    <th class="border-bottom">Fecha</th>
                    <th class="border-bottom">Descripción</th>
                    <th class="border-bottom text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                  <td class="border-bottom">{{\Carbon\Carbon::parse($recibo->fecha)->format('d/m/Y')}}</td>
                  <td class="border-bottom">{{$recibo->concepto}}</td>
                  <td class="text-right border-bottom">${{number_format($recibo->total,2)}}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="1"></td>
                    <td class="text-right">Total</td>
                    <td class="text-right">${{ number_format($venta->total, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="1"></td>
                    <td class="text-right">Abonos</td>
                    <td class="text-right">${{ number_format($venta->abonos()->sum('total'), 2) }}</td>
                </tr>
                <tr>
                    <td colspan="1"></td>
                    <td class="text-right"><b>Saldo</b></td>
                    <td class="text-right"><b>${{ number_format($venta->saldo, 2) }}</b></td>
                </tr>
            </tfoot>
        </table>

    </section>


</body>
</html>
