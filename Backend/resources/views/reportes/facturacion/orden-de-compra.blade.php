<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Orden de compra #{{ $compra->referencia }} - {{ $compra->nombre_proveedor }}</title>
    <style>
        * {
            margin: 0cm;
            font-family: 'system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue","Noto Sans","Liberation Sans",Arial,sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji"';
        }

        body {
            font-family: serif;
            margin: 50px;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            color: #005CBB !important;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            border: 0px;
            border-collapse: collapse;
            padding: 10px 5px;
            text-align: left;
        }

        .text-right {
            text-align: right !important;
        }

        .border-bottom {
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
                    <h1>{{ $compra->empresa()->pluck('nombre')->first() }}</h1>
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
                <td>
                    <h4>Proveedor</h4>
                </td>
            </tr>
            <tr>
                <td>
                    <p>{{ $compra->nombre_proveedor }}</p>
                    <p>
                        {{ $compra->proveedor()->pluck('municipio')->first() }}
                        {{ $compra->proveedor()->pluck('departamento')->first() }}
                        {{ $compra->proveedor()->pluck('direccion')->first() }} <br>
                    </p>
                </td>
                <td>
                    <p>NCR:{{ $compra->proveedor()->pluck('ncr')->first() }}</p>
                    <p>DUI:{{ $compra->proveedor()->pluck('dui')->first() }}</p>
                    <p>Teléfono:{{ $compra->proveedor()->pluck('telefono')->first() }}</p>
                </td>
                <td>
                    <p class="text-right">Orden de compra #{{ $compra->referencia }}</p>
                    <p class="text-right">Fecha: {{ \Carbon\Carbon::parse($compra->fecha)->format('d/m/Y') }}</p>
                    <p class="text-right">Válido hasta: {{ \Carbon\Carbon::parse($compra->fecha_expiracion)->format('d/m/Y') }}</p>
                </td>
            </tr>
            @if ($compra->nombre_proyecto)
            <tr>
                <td>
                    <h4>Proyecto</h4>
                </td>
            </tr>
            <tr>
                <td>{{ $compra->nombre_proyecto ?? 'N/A' }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <br>

    <table class="table">
        <thead>
            <tr>
                <th class="border-bottom">Descripción</th>
                <th class="border-bottom">Código</th>
                <th class="border-bottom text-right">Cantidad</th>
                <th class="border-bottom text-right">Precio</th>
                <th class="border-bottom text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($compra->detalles as $detalle)
            <tr>
                <td class="border-bottom"> {{ $detalle->nombre_producto  }}</td>
                <td class="border-bottom"> {{ $detalle->codigo }}</td>
                <td class="border-bottom text-right"> {{ number_format($detalle->cantidad, 0) }}</td>
                <td class="border-bottom text-right"> {{ $compra->empresa->currency->currency_symbol }} {{ number_format($detalle->costo , 2) }}</td>
                <td class="border-bottom text-right"> {{ $compra->empresa->currency->currency_symbol }} {{ number_format($detalle->total, 2) }}</th>
            </tr>
            @if ($detalle->descuento > 0)
            <tr>
                <td>DESCUENTOS</td>
                <td></td>
                <td></td>
                <td class="text-right">- {{ $compra->empresa->currency->currency_symbol }} {{ number_format($detalle->descuento, 2) }} </th>
            </tr>
            @endif
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2"></td>
                <td class="text-right">Sumas</td>
                <td class="text-right">{{ $compra->empresa->currency->currency_symbol }} {{ number_format($compra->sub_total, 2) }}</td>
            </tr>
            <tr>
                <td colspan="2"></td>
                <td class="text-right">IVA</td>
                <td class="text-right">{{ $compra->empresa->currency->currency_symbol }} {{ number_format($compra->iva, 2) }}</td>
            </tr>
            <tr>
                <td colspan="2"></td>
                <td class="text-right">Subtotal</td>
                <td class="text-right">{{ $compra->empresa->currency->currency_symbol }} {{ number_format($compra->sub_total + $compra->iva, 2) }}</td>
            </tr>
            @if ($compra->percepcion)
            <tr>
                <td colspan="2"></td>
                <td class="text-right">Percepción (1%)</td>
                <td class="text-right">{{ $compra->empresa->currency->currency_symbol }} {{ number_format($compra->percepcion, 2) }}</td>
            </tr>
            @endif
            <tr>
                <td colspan="2"></td>
                <td class="text-right"><b>Total</b></td>
                <td class="text-right"><b>{{ $compra->empresa->currency->currency_symbol }} {{ number_format($compra->total, 2) }}</b></td>
            </tr>
        </tfoot>
    </table>

    <br>
    <h4>Términos y condiciones:</h4>
    <p>{{ $compra->observaciones }}</p>
    <br>

    @if($compra->empresa->mostrar_sello_firma)
    <table style="width: 100%; margin-top: 30px; border-top: 1px solid #ddd;">
        <tr>
            <td style="width: 50%; padding: 20px; text-align: center;">
                @if($compra->empresa->firma)
                <img 
                    src="{{ asset('img/'.$compra->empresa->firma) }}" 
                    alt="Firma" 
                    style="max-width: 150px; max-height: 100px;">
                @else
                <div style="height: 100px; line-height: 100px;">(Sin firma)</div>
                @endif
            </td>
            <td style="width: 50%; padding: 20px; text-align: center;">
                @if($compra->empresa->sello)
                <img 
                    src="{{ asset('img/'.$compra->empresa->sello) }}" 
                    alt="Sello" 
                    style="max-width: 150px; max-height: 100px;">
                @else
                <div style="height: 100px; line-height: 100px;">(Sin sello)</div>
                @endif
            </td>
        </tr>
        <tr>
            <td style="width: 50%; padding: 10px; text-align: center;">
                <h4 style="margin: 0; font-size: 16px; color: #333;">Firma</h4>
            </td>
            <td style="width: 50%; padding: 10px; text-align: center;">
                <h4 style="margin: 0; font-size: 16px; color: #333;">Sello</h4>
            </td>
        </tr>
    </table>
    @endif


</body>

</html>