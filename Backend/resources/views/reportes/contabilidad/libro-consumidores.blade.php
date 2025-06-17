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
                <th>Fecha</th>
                <th>Correlativo</th>
                <th class="text-right">Ventas Exentas</th>
                <th class="text-right">Ventas No Sujetas</th>
                <th class="text-right">Ventas Gravadas</th>
                <th class="text-right">Exportaciones</th>
                <th class="text-right">Total</th>
                <th class="text-right">Cuenta a terceros</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($libroconsumidores as $venta)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ Carbon\Carbon::parse($venta['fecha'])->format('d/m/Y') }}</td>
                    <td>{{ $venta['correlativo'] }}</td>
                    <td class="text-right">${{ $venta['ventas_exentas'] }}</td>
                    <td class="text-right">${{ $venta['no_sujeta'] }}</td>
                    <td class="text-right">${{ $venta['ventas_gravadas'] }}</td>
                    <td class="text-right">${{ $venta['exportaciones'] }}</td>
                    <td class="text-right">${{ $venta['total'] }}</td>
                    <td class="text-right">${{ $venta['cuenta_a_terceros'] }}</td>
                </tr>
            @endforeach
        </tbody>  
    </table>

</body>
</html>
