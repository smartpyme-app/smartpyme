<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Libro Consumidores</title>

    <style>
        body{
            font-family: Arial, sans-serif;
            font-size: 10px;
        }

        h1, h2{
            margin: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        thead th {
            padding: 3px;
            text-align: left;
            border: 1px solid #dee2e6;
        }

        tbody td {
            padding: 3px;
            text-align: left;
            border: 1px solid #dee2e6;
        }
        td, th {
            vertical-align: middle;
        }

        .text-center{text-align: center; }
        .text-right{text-align: right; }

    </style>
</head>
<body>
    @php $empresa = Auth::user()->empresa()->with('currency')->first(); $simbolo_moneda = ($empresa && $empresa->currency) ? $empresa->currency->currency_symbol : '$'; @endphp

    <h1 class="text-center">LIBRO DE VENTAS A CONSUMIDORES</h1>
    <h2 class="text-center">{{ Auth::user()->empresa()->pluck('nombre')->first() }}</h2>
    <table>
        <tr>
            <td><b>NRC:</b> {{ Auth::user()->empresa()->pluck('ncr')->first() }}</td>
            <td><b>Folio N°:</b> </td>
        </tr>
        <tr>
            <td><b>Mes:</b> {{ ucfirst(Carbon\Carbon::parse($request->inicio)->translatedFormat('F')) }}</td>
            <td><b>Año:</b> {{ Carbon\Carbon::parse($request->inicio)->format('Y') }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>N°</th>
                <th>FECHA</th>
                <th>CORRELATIVO INICIAL</th>
                <th>CORRELATIVO FINAL</th>
                <th class="text-right">VENTAS EXENTAS</th>
                <th class="text-right">VENTAS INTERNAS GRAVADAS</th>
                <th class="text-right">EXPORTACIONES</th>
                <th class="text-right">TOTAL DE VENTAS DIARIAS PROPIAS</th>
                <th class="text-right">VENTAS A CUENTA DE TERCEROS</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($libroconsumidores as $venta)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ Carbon\Carbon::parse($venta['fecha'])->format('d/m/Y') }}</td>
                    <td>{{ $venta['correlativo_inicial'] }}</td>
                    <td>{{ $venta['correlativo_final'] }}</td>
                    <td class="text-right">{{ $simbolo_moneda }}{{ number_format($venta['ventas_exentas'], 2) }}</td>
                    <td class="text-right">{{ $simbolo_moneda }}{{ number_format($venta['ventas_internas_gravadas'], 2) }}</td>
                    <td class="text-right">{{ $simbolo_moneda }}{{ number_format($venta['exportaciones'], 2) }}</td>
                    <td class="text-right">{{ $simbolo_moneda }}{{ number_format($venta['total_ventas_diarias_propias'], 2) }}</td>
                    <td class="text-right">{{ $simbolo_moneda }}{{ number_format($venta['ventas_a_cuenta_de_terceros'], 2) }}</td>
                </tr>
            @endforeach
            @if(isset($totalesConsumidores))
                <tr>
                    <td colspan="4" class="text-center"><b>Totales</b></td>
                    <td class="text-right"><b>{{ $simbolo_moneda }}{{ number_format($totalesConsumidores['ventas_exentas'], 2) }}</b></td>
                    <td class="text-right"><b>{{ $simbolo_moneda }}{{ number_format($totalesConsumidores['ventas_internas_gravadas'], 2) }}</b></td>
                    <td class="text-right"><b>{{ $simbolo_moneda }}{{ number_format($totalesConsumidores['exportaciones'], 2) }}</b></td>
                    <td class="text-right"><b>{{ $simbolo_moneda }}{{ number_format($totalesConsumidores['total_ventas_diarias_propias'], 2) }}</b></td>
                    <td class="text-right"><b>{{ $simbolo_moneda }}{{ number_format($totalesConsumidores['ventas_a_cuenta_de_terceros'], 2) }}</b></td>
                </tr>
            @endif
        </tbody>  
    </table>

</body>
</html>
