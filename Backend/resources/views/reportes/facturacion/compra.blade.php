<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Compra #{{ $compra->referencia }} - {{ $compra->nombre_proveedor }}</title>
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
            color: #000000 !important;
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
            border-bottom: 1px solid #000000 !important;
        }


    </style>

</head>
<body>
{{-- <body onload="javascript:print();"> --}}

        <table>
            <tbody>
                <tr>
                    <td>
                        <h3>{{ $compra->empresa()->pluck('nombre')->first() }}</h3>
                        <p>
                            {{ $compra->empresa()->pluck('municipio')->first() }}
                            {{ $compra->empresa()->pluck('departamento')->first() }}
                        </p>
                        <p>{{ $compra->empresa()->pluck('direccion')->first() }}</p>
                        <p>{{ $compra->empresa()->pluck('telefono')->first() }}</p>
                    </td>
                    <td class="text-right">
                        @if ($compra->empresa()->pluck('logo')->first())
                            <img width="150" height="150" src="{{ asset('img/'.$compra->empresa()->pluck('logo')->first()) }}" alt="Logo">
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>

        <table>
            <tbody>
                <tr>
                    <td><h4>Proveedor</h4></td>
                </tr>
                <tr>
                    <td>
                        <p>{{ $compra->nombre_proveedor }}</p>
                        <p>
                        @if ($compra->empresa()->pluck('id')->first() != 420)
                            {{ $compra->proveedor()->pluck('municipio')->first() }}
                            {{ $compra->proveedor()->pluck('departamento')->first() }}
                        @endif
                            {{ $compra->proveedor()->pluck('direccion')->first() }} <br>
                        </p>
                    </td>
                    <td>
                        
                        @if ($compra->empresa()->pluck('id')->first() != 420)
                            <p>NCR:{{ $compra->proveedor()->pluck('ncr')->first() }}</p>
                            <p>DUI:{{ $compra->proveedor()->pluck('dui')->first() }}</p>
                            <p>Teléfono:{{ $compra->proveedor()->pluck('telefono')->first() }}</p>
                        @else
                            <p>RTN:{{ $compra->proveedor()->pluck('ncr')->first() }}</p>
                            <p>Teléfono:{{ $compra->proveedor()->pluck('telefono')->first() }}</p>
                        @endif
                    </td>
                    <td>
                        <p class="text-right">{{ $compra->tipo_documento }} #{{ $compra->referencia }}</p>
                        <p class="text-right">Fecha: {{ \Carbon\Carbon::parse($compra->fecha)->format('d/m/Y') }}</p>
                        @if ($compra->fecha_pago)
                            <p class="text-right">Fecha pago: {{ \Carbon\Carbon::parse($compra->fecha_pago)->format('d/m/Y') }}</p>
                        @endif
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
                    <th class="border-bottom text-right">Costo</th>
                    <th class="border-bottom text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($compra->detalles as $detalle)
                <tr>
                    <td class="border-bottom">   {{ $detalle->nombre_producto  }}</td>
                    <td class="border-bottom text-right">   {{ number_format($detalle->cantidad, 0) }}</td>
                    <td class="border-bottom text-right">   {{ $compra->empresa->currency->currency_symbol }}{{number_format($detalle->costo , 2) }}</td>
                    <td class="border-bottom text-right">   {{ $compra->empresa->currency->currency_symbol }}{{ number_format($detalle->total, 2) }}</th>
                </tr>
                @if ($detalle->descuento > 0)
                    <tr>
                        <td>DESCUENTOS</td>
                        <td></td>
                        <td></td>
                        <td class="text-right">- {{ $compra->empresa->currency->currency_symbol }}{{ number_format($detalle->descuento, 2) }} </th>
                    </tr>
                @endif
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2"></td>
                    <td class="text-right">Sumas</td>
                    <td class="text-right">{{ $compra->empresa->currency->currency_symbol }}{{ number_format($compra->sub_total, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="2"></td>
                    <td class="text-right">
                        @if ($compra->empresa->pais == 'Honduras')
                            ISV
                        @else
                            IVA
                        @endif
                    </td>
                    <td class="text-right">{{ $compra->empresa->currency->currency_symbol }}{{ number_format($compra->iva, 2) }}</td>
                </tr>
                @if ($compra->percepcion > 0)
                <tr>
                    <td colspan="2"></td>
                    <td class="text-right">Percepción (1%)</td>
                    <td class="text-right">{{ $compra->empresa->currency->currency_symbol }}{{ number_format($compra->percepcion, 2) }}</td>
                </tr>
                @endif
                @if ($compra->renta_retenida > 0)
                <tr>
                    <td colspan="2"></td>
                    <td class="text-right">Renta retenida</td>
                    <td class="text-right">{{ $compra->empresa->currency->currency_symbol }}{{ number_format($compra->renta_retenida, 2) }}</td>
                </tr>
                @endif
                <tr>
                    <td colspan="2"></td>
                    <td class="text-right">Subtotal</td>
                    <td class="text-right">{{ $compra->empresa->currency->currency_symbol }}{{ number_format($compra->sub_total + $compra->iva, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="2"></td>
                    <td class="text-right"><b>Total</b></td>
                    <td class="text-right"><b>{{ $compra->empresa->currency->currency_symbol }}{{ number_format($compra->total, 2) }}</b></td>
                </tr>
            </tfoot>
        </table>

        <br>
        @if($compra->observaciones)
        <h4>Observaciones:</h4>
        <p>{!! nl2br(e($compra->observaciones))  !!} </p>
        <br>
        @endif

    </section>


</body>
</html>
