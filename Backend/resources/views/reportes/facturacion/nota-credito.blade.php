<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Nota de crédito #{{ $venta->id }}</title>
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
    @php $simbolo_moneda = ($venta->empresa && $venta->empresa->currency) ? $venta->empresa->currency->currency_symbol : '$'; @endphp

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
                    {{-- <td class="text-right">
                        @if ($venta->empresa()->pluck('logo')->first())
                            <img width="150" height="150" src="{{ asset('img/'.$venta->empresa()->pluck('logo')->first()) }}" alt="Logo">
                        @endif
                    </td> --}}
                </tr>
            </tbody>
        </table>
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
                        @if($venta->empresa->pais == 'El Salvador')
                            <p>NCR:{{ $venta->cliente()->pluck('ncr')->first() }}</p>
                            <p>DUI:{{ $venta->cliente()->pluck('dui')->first() }}</p>
                        @else
                            <p>Registro tributario:{{ $venta->cliente()->pluck('ncr')->first() }}</p>
                            <p>Número de identificación:{{ $venta->cliente()->pluck('dui')->first() }}</p>
                        @endif
                        <p>Teléfono:{{ $venta->cliente()->pluck('telefono')->first() }}</p>
                    </td>
                    <td>
                        <p class="text-right">Nota de crédito #{{ $venta->correlativo }}</p>
                        <p class="text-right">Fecha: {{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
                    </td>
                </tr>
            </tbody>
        </table> 

        <br>

        <table class="table">
            <thead>
                <tr>
                    <th class="border-bottom">Descripción</th>
                    <th class="border-bottom text-right">Cantidad</th>
                    <th class="border-bottom text-right">Precio</th>
                    <th class="border-bottom text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($venta->detalles as $detalle)
                <tr>
                    <td class="border-bottom">   {{ $detalle->nombre_producto  }}</td>
                    <td class="border-bottom text-right">   {{ number_format($detalle->cantidad, 0) }}</td>
                    <td class="border-bottom text-right">   {{ $simbolo_moneda }}{{ number_format($detalle->precio , 2) }}</td>
                    <td class="border-bottom text-right">   {{ $simbolo_moneda }}{{ number_format($detalle->total, 2) }}</th>
                </tr>
                @if ($detalle->descuento > 0)
                    <tr>
                        <td>DESCUENTOS</td>
                        <td></td>
                        <td></td>
                        <td class="text-right">- {{ $simbolo_moneda }}{{ number_format($detalle->descuento, 2) }} </th>
                    </tr>
                @endif
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2"></td>
                    <td class="text-right">Subtotal</td>
                    <td class="text-right">{{ $simbolo_moneda }}{{ number_format($venta->sub_total, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="2"></td>
                    <td class="text-right">IVA</td>
                    <td class="text-right">{{ $simbolo_moneda }}{{ number_format($venta->iva, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="2"></td>
                    <td class="text-right"><b>Total</b></td>
                    <td class="text-right"><b>{{ $simbolo_moneda }}{{ number_format($venta->total, 2) }}</b></td>
                </tr>
            </tfoot>
        </table>

        <br>
        <h4>Observaciones:</h4>
        <p>{{ $venta->observaciones }}</p>
        <br>

    </section>


</body>
</html>
