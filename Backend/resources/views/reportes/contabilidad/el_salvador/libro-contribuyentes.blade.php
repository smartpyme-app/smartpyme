<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Libro Contribuyentes</title>

    <style>
        body{ font-family: Arial, sans-serif; font-size: 10px; }
        h1, h2{ margin: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        thead th, tbody td { padding: 3px; text-align: left; border: 1px solid #dee2e6; }
        td, th { vertical-align: middle; }
        .text-center{text-align: center; }
        .text-right{text-align: right; }
    </style>
</head>
<body>
    @php $empresa = Auth::user()->empresa()->with('currency')->first(); $simbolo_moneda = ($empresa && $empresa->currency) ? $empresa->currency->currency_symbol : '$'; @endphp

    <h1 class="text-center">LIBRO DE VENTAS A CONTRIBUYENTES</h1>
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
                <th>CÓDIGO DE GENERACIÓN</th>
                <th>NÚMERO DE CONTROL</th>
                <th>SELLO</th>
                <th>NOMBRE DEL CLIENTE MANDANTE O MANDATARIO</th>
                <th>NRC DEL CLIENTE</th>
                <th class="text-right">VENTAS EXENTAS</th>
                <th class="text-right">VENTAS INTERNAS GRAVADAS</th>
                <th class="text-right">DÉBITO FISCAL</th>
                <th class="text-right">VENTAS EXENTAS A CUENTA DE TERCEROS</th>
                <th class="text-right">VENTAS INTERNAS GRAVADAS A CUENTA DE TERCEROS</th>
                <th class="text-right">DEBITO FISCAL POR CUENTA DE TERCEROS</th>
                <th class="text-right">IVA RETENIDO</th>
                <th class="text-right">IVA PERCIBIDO</th>
                <th class="text-right">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($librocontribuyentes as $venta)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ Carbon\Carbon::parse($venta['fecha'])->format('d/m/Y') }}</td>
                    <td>{{ $venta['codigo_generacion'] }}</td>
                    <td>{{ $venta['numero_control'] }}</td>
                    <td>{{ $venta['sello'] }}</td>
                    <td>{{ $venta['nombre_cliente'] }}</td>
                    <td>{{ $venta['nrc_cliente'] }}</td>
                    <td class="text-right">{{ $simbolo_moneda }}{{ number_format($venta['ventas_exentas'], 2) }}</td>
                    <td class="text-right">{{ $simbolo_moneda }}{{ number_format($venta['ventas_internas_gravadas'], 2) }}</td>
                    <td class="text-right">{{ $simbolo_moneda }}{{ number_format($venta['debito_fiscal'], 2) }}</td>
                    <td class="text-right">{{ $simbolo_moneda }}{{ number_format($venta['ventas_exentas_a_cuenta_de_terceros'], 2) }}</td>
                    <td class="text-right">{{ $simbolo_moneda }}{{ number_format($venta['ventas_internas_gravadas_a_cuenta_de_terceros'], 2) }}</td>
                    <td class="text-right">{{ $simbolo_moneda }}{{ number_format($venta['debito_fiscal_por_cuenta_de_terceros'], 2) }}</td>
                    <td class="text-right">{{ $simbolo_moneda }}{{ number_format($venta['iva_retenido'], 2) }}</td>
                    <td class="text-right">{{ $simbolo_moneda }}{{ number_format($venta['iva_percibido'], 2) }}</td>
                    <td class="text-right">{{ $simbolo_moneda }}{{ number_format($venta['total'], 2) }}</td>
                </tr>
            @endforeach
            @if(isset($totalesContribuyentes))
                <tr>
                    <td colspan="7" class="text-center"><b>Totales</b></td>
                    <td class="text-right"><b>{{ $simbolo_moneda }}{{ number_format($totalesContribuyentes['ventas_exentas'], 2) }}</b></td>
                    <td class="text-right"><b>{{ $simbolo_moneda }}{{ number_format($totalesContribuyentes['ventas_internas_gravadas'], 2) }}</b></td>
                    <td class="text-right"><b>{{ $simbolo_moneda }}{{ number_format($totalesContribuyentes['debito_fiscal'], 2) }}</b></td>
                    <td class="text-right"><b>{{ $simbolo_moneda }}{{ number_format($totalesContribuyentes['ventas_exentas_a_cuenta_de_terceros'], 2) }}</b></td>
                    <td class="text-right"><b>{{ $simbolo_moneda }}{{ number_format($totalesContribuyentes['ventas_internas_gravadas_a_cuenta_de_terceros'], 2) }}</b></td>
                    <td class="text-right"><b>{{ $simbolo_moneda }}{{ number_format($totalesContribuyentes['debito_fiscal_por_cuenta_de_terceros'], 2) }}</b></td>
                    <td class="text-right"><b>{{ $simbolo_moneda }}{{ number_format($totalesContribuyentes['iva_retenido'], 2) }}</b></td>
                    <td class="text-right"><b>{{ $simbolo_moneda }}{{ number_format($totalesContribuyentes['iva_percibido'], 2) }}</b></td>
                    <td class="text-right"><b>{{ $simbolo_moneda }}{{ number_format($totalesContribuyentes['total'], 2) }}</b></td>
                </tr>
            @endif
        </tbody>
    </table>

</body>
</html>
