<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Documento de entrega - Traslado</title>
    <style>
        *{
            margin: 0cm;
            font-family: 'system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue","Noto Sans","Liberation Sans",Arial,sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji"';
        }
        body {
            font-family: serif;
            margin: 50px 50px;
        }
        h1,h2,h3,h4,h5,h6{
            color: #000000 !important;
        }
        p{
            font-size: 14px;
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
    @php
        $primer = $traslados->first();
        $simbolo = optional($empresa->currency)->currency_symbol ?? '$';
        $totalCosto = $traslados->sum(function ($t) {
            return ($t->costo ?? 0) * $t->cantidad;
        });
        $origen = $primer->origen->nombre ?? $primer->nombre_origen ?? 'N/A';
        $destino = $primer->destino->nombre ?? $primer->nombre_destino ?? 'N/A';
    @endphp

        <table>
            <tbody>
                <tr>
                    <td width="60%">
                        <h3 style="font-size: 20px;">{{ $empresa->nombre }}</h3>
                        <p>
                            {{ $empresa->municipio }}
                            {{ $empresa->departamento }}
                        </p>
                        <p>{{ $empresa->direccion }}</p>
                        <p>{{ $empresa->telefono }}</p>
                    </td>
                    <td class="text-right" width="40%" style="vertical-align: top;">
                        @if ($empresa->logo)
                            <img src="{{ asset('img/'.$empresa->logo) }}" alt="Logo"
                                 style="width: 90px; height: 90px; object-fit: contain;">
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>

        <table>
            <tbody>
                <tr>
                    <td><h4>Documento de entrega — Traslado de inventario</h4></td>
                </tr>
                <tr>
                    <td>
                        <p><b>De:</b> {{ $origen }}</p>
                        <p><b>Para:</b> {{ $destino }}</p>
                    </td>
                    <td>
                        <p>Realizado por: {{ $primer->usuario->name ?? 'N/A' }}</p>
                        <p>Estado: {{ $primer->estado }}</p>
                        <p>Productos: {{ $traslados->count() }}</p>
                    </td>
                    <td>
                        <p class="text-right">Fecha: {{ \Carbon\Carbon::parse($primer->created_at)->format('d/m/Y') }}</p>
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
                @foreach($traslados as $traslado)
                <tr>
                    <td class="border-bottom">
                        {{ $traslado->producto->nombre ?? $traslado->nombre_producto ?? 'N/A' }}
                        @if($traslado->producto && !empty($traslado->producto->nombre_variante))
                            - {{ $traslado->producto->nombre_variante }}
                        @endif
                    </td>
                    <td class="border-bottom text-right">{{ number_format($traslado->cantidad, 0) }}</td>
                    <td class="border-bottom text-right">{{ $simbolo }} {{ number_format($traslado->costo ?? 0, 2) }}</td>
                    <td class="border-bottom text-right">{{ $simbolo }} {{ number_format(($traslado->costo ?? 0) * $traslado->cantidad, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2"></td>
                    <td class="text-right"><b>Total</b></td>
                    <td class="text-right"><b>{{ $simbolo }} {{ number_format($totalCosto, 2) }}</b></td>
                </tr>
            </tfoot>
        </table>

        <br>
        @if($primer->concepto)
        <h4>Concepto:</h4>
        <p>{!! nl2br(e($primer->concepto)) !!}</p>
        <br>
        @endif

        <table style="width: 100%; margin-top: 30px;">
            <tr>
                <td style="width: 50%; padding: 10px; text-align: center;">
                    <p>____________________________</p>
                    <h4 style="margin: 0; font-size: 16px; color: #333;">Entregado por</h4>
                    <p>{{ $origen }}</p>
                </td>
                <td style="width: 50%; padding: 10px; text-align: center;">
                    <p>____________________________</p>
                    <h4 style="margin: 0; font-size: 16px; color: #333;">Recibido por</h4>
                    <p>{{ $destino }}</p>
                </td>
            </tr>
        </table>

</body>
</html>
