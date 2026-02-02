<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Traslado #{{ $traslado->id }} - {{ $traslado->nombre_producto }}</title>
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
                        <h1>{{ $empresa->nombre }}</h1>
                        <p>
                            {{ $empresa->municipio }}
                            {{ $empresa->departamento }}
                        </p>
                        <p>{{ $empresa->direccion }}</p>
                        <p>{{ $empresa->telefono }}</p>
                    </td>
                    <td class="text-right">
                        @if ($empresa->logo)
                            <img width="150" height="150" src="{{ asset('img/'.$empresa->logo) }}" alt="Logo">
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>

        <table>
            <tbody>
                <tr>
                    <td><h4>Traslado de Inventario</h4></td>
                </tr>
                <tr>
                    <td>
                        <p><b>Producto:</b> {{ $traslado->nombre_producto }}</p>
                        <p><b>Concepto:</b> {{ $traslado->concepto ?? 'N/A' }}</p>
                    </td>
                    <td>
                        <p><b>De:</b> {{ $traslado->nombre_origen }}</p>
                        <p><b>Para:</b> {{ $traslado->nombre_destino }}</p>
                    </td>
                    <td>
                        <p class="text-right">Traslado #{{ $traslado->id }}</p>
                        <p class="text-right">Fecha: {{ \Carbon\Carbon::parse($traslado->created_at)->format('d/m/Y') }}</p>
                        <p class="text-right">Estado: {{ $traslado->estado }}</p>
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
                    <th class="border-bottom text-right">Costo Unitario</th>
                    <th class="border-bottom text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="border-bottom">{{ $traslado->nombre_producto }}</td>
                    <td class="border-bottom text-right">{{ number_format($traslado->cantidad, 0) }}</td>
                    <td class="border-bottom text-right">{{ $empresa->currency->currency_symbol ?? '$' }}{{ number_format($traslado->costo ?? 0, 2) }}</td>
                    <td class="border-bottom text-right">{{ $empresa->currency->currency_symbol ?? '$' }}{{ number_format(($traslado->costo ?? 0) * $traslado->cantidad, 2) }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-right"><b>Total</b></td>
                    <td class="text-right"><b>{{ $empresa->currency->currency_symbol ?? '$' }}{{ number_format(($traslado->costo ?? 0) * $traslado->cantidad, 2) }}</b></td>
                </tr>
            </tfoot>
        </table>

        <br>
        @if($traslado->concepto)
        <h4>Concepto:</h4>
        <p>{!! nl2br(e($traslado->concepto))  !!} </p>
        <br>
        @endif

    </section>


</body>
</html>
