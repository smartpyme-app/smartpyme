<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Libro Contribuyentes</title>

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
                <th>FECHA DE EMISIÓN DEL DOCUMENTO</th>
                <th>NÚMERO DE CORRELATIVO PREEIMPRESO</th>
                <th>NÚMERO DE CONTROL INTERNO SISTEMA FORMULARIO ÚNICO</th>
                <th>NOMBRE DEL CLIENTE MANDANTE O MANDATARIO</th>
                <th>NRC DEL CLIENTE</th>
                <th class="text-right">VENTAS EXENTAS</th>
                <th class="text-right">VENTAS INTERNAS GRAVADAS</th>
                <th class="text-right">DEÉBITO FISCAL</th>
                <th class="text-right">VENTAS EXENTAS A CUENTA DE TERCEROS</th>
                <th class="text-right">VENTAS INTERNAS GRAVADAS A CUENTA DE TERCEROS</th>
                <th class="text-right">DEBITO FISCAL POR CUENTA DE TERCEROS</th>
                <th class="text-right">IVA PERCIBIDO</th>
                <th class="text-right">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($librocontribuyentes as $venta)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ Carbon\Carbon::parse($venta['fecha'])->format('d/m/Y') }}</td>
                    <td>{{ $venta['correlativo'] }}</td>
                    <td>{{ $venta['num_documento'] }}</td>
                    <td>{{ $venta['nombre_cliente'] }}</td>
                    <td>{{ $venta['nit_nrc'] }}</td>
                    <td class="text-right">${{ $venta['ventas_exentas'] }}</td>
                    <td class="text-right">${{ $venta['ventas_gravadas'] }}</td>
                    <td class="text-right">${{ $venta['debito_fiscal'] }}</td>
                    <td class="text-right">${{ $venta['ventas_exentas_cuenta_a_terceros'] }}</td>
                    <td class="text-right">${{ $venta['ventas_gravadas_cuenta_a_terceros'] }}</td>
                    <td class="text-right">${{ $venta['debito_fiscal_cuenta_a_terceros'] }}</td>
                    <td class="text-right">${{ $venta['iva_percibido'] }}</td>
                    <td class="text-right">${{ $venta['total'] }}</td>
                </tr>
            @endforeach
        </tbody>  
    </table>

</body>
</html>
