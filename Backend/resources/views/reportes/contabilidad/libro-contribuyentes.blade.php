<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Libro Contribuyentes</title>

    <style>
        body{
            font-family: san-serif;
            font-size: 10px;
        }
    </style>
</head>
<body>

    <h1>Libro de Contribuyentes</h1>

    <h3>LIBRO DE VENTAS A CONSUMIDORES '</h3>
    <h3>{{ Auth::user()->empresa()->pluck('nombre')->first() }}</h3>
    <h3>NRC: {{ Auth::user()->empresa()->pluck('ncr')->first() }}</h3>
    <h3>Folio N°:</h3>
    <h3>Mes: {{ ucfirst(Carbon\Carbon::parse($request->inicio)->translatedFormat('F')) }}</h3>
    <h3>Año: {{ Carbon\Carbon::parse($request->inicio)->format('Y') }}</h3>
    

    <table>
        <thead>
            <tr>
                <th>N°</th>
                <th width="100px">Fecha</th>
                <th>Correlativo</th>
                <th>Número de control interno</th>
                <th>Cliente</th>
                <th>NIT/NRC</th>
                <th>Ventas Exentas</th>
                <th>Ventas No Sujetas</th>
                <th>Ventas Gravadas</th>
                <th>Débito Fiscal</th>
                <th>Ventas Exentas a Cuenta de Terceros</th>
                <th>Ventas Gravadas a Cuenta de Terceros</th>
                <th>Débito Fiscal por Cuenta de Terceros</th>
                <th>IVA Retenido</th>
                <th>IVA Percibido</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($librocontribuyentes as $venta)
                <tr>
                    <td>{{ $venta['fecha'] }}</td>
                    <td>{{ $venta['correlativo'] }}</td>
                    <td>{{ $venta['num_documento'] }}</td>
                    <td>{{ $venta['nombre_cliente'] }}</td>
                    <td>{{ $venta['nit_nrc'] }}</td>
                    <td>{{ $venta['ventas_exentas'] }}</td>
                    <td>{{ $venta['ventas_no_sujetas'] }}</td>
                    <td>{{ $venta['ventas_gravadas'] }}</td>
                    <td>{{ $venta['cuenta_a_terceros'] }}</td>
                    <td>{{ $venta['debito_fiscal'] }}</td>
                    <td>{{ $venta['ventas_exentas_cuenta_a_terceros'] }}</td>
                    <td>{{ $venta['ventas_gravadas_cuenta_a_terceros'] }}</td>
                    <td>{{ $venta['debito_fiscal_cuenta_a_terceros'] }}</td>
                    <td>{{ $venta['debito_fiscal_cuenta_a_terceros'] }}</td>
                    <td>{{ $venta['iva_retenido'] }}</td>
                    <td>{{ $venta['iva_percibido'] }}</td>
                    <td>{{ $venta['total'] }}</td>
                </tr>
            @endforeach
        </tbody>  
    </table>

</body>
</html>
