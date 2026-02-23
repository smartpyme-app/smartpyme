<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Libro Compras</title>

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

        tfoot td {
            padding: 3px;
            text-align: left;
            border: 1px solid #dee2e6;
            background-color: #f8f9fa;
            font-weight: bold;
        }

        td, th {
            vertical-align: middle;
        }

        .text-center{text-align: center; }
        .text-right{text-align: right; }

    </style>
</head>
<body>

    <h1 class="text-center">LIBRO DE COMPRAS</h1>
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
                <th>NÚMERO DE DOCUMENTO</th>
                <th>NÚMERO DE REGISTRO DEL CONTRIBUYENTE</th>
                <th>NOMBRE DEL PROVEEDOR</th>
                <th class="text-right">COMPRAS EXENTAS INTERNAS</th>
                <th class="text-right">IMPORTACIONES E INTERNACIONES EXENTAS</th>
                <th class="text-right">COMPRAS INTERNAS GRAVADAS</th>
                <th class="text-right">IMPORTACIONES E INTERNACIONES GRAVADAS</th>
                <th class="text-right">CRÉDITO FISCAL</th>
                <th class="text-right">ANTICIPO A CUENTA IVA PERCIBIDO</th>
                <th class="text-right">TOTAL DE COMPRAS</th>
                <th class="text-right">COMPRAS A SUJETOS EXCLUIDOS</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($librocompras as $venta)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ Carbon\Carbon::parse($venta['fecha'])->format('d/m/Y') }}</td>
                    <td>{{ $venta['num_documento'] }}</td>
                    <td>{{ $venta['nit_nrc'] }}</td>
                    <td>{{ $venta['nombre_proveedor'] }}</td>
                    <td class="text-right">${{ number_format($venta['compras_exentas'], 2) }}</td>
                    <td class="text-right">${{ number_format($venta['importaciones_exentas'], 2) }}</td>
                    <td class="text-right">${{ number_format($venta['compras_gravadas'], 2) }}</td>
                    <td class="text-right">${{ number_format($venta['importaciones_gravadas'], 2) }}</td>
                    <td class="text-right">${{ number_format($venta['credito_fiscal'], 2) }}</td>
                    <td class="text-right">${{ number_format($venta['anticipo_iva_percibido'], 2) }}</td>
                    <td class="text-right">${{ number_format($venta['total'], 2) }}</td>
                    <td class="text-right">${{ number_format($venta['sujeto_excluido'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5"><b>TOTALES</b></td>
                <td class="text-right"><b>${{ number_format(collect($librocompras)->sum('compras_exentas'), 2) }}</b></td>
                <td class="text-right"><b>${{ number_format(collect($librocompras)->sum('importaciones_exentas'), 2) }}</b></td>
                <td class="text-right"><b>${{ number_format(collect($librocompras)->sum('compras_gravadas'), 2) }}</b></td>
                <td class="text-right"><b>${{ number_format(collect($librocompras)->sum('importaciones_gravadas'), 2) }}</b></td>
                <td class="text-right"><b>${{ number_format(collect($librocompras)->sum('credito_fiscal'), 2) }}</b></td>
                <td class="text-right"><b>${{ number_format(collect($librocompras)->sum('anticipo_iva_percibido'), 2) }}</b></td>
                <td class="text-right"><b>${{ number_format(collect($librocompras)->sum('total'), 2) }}</b></td>
                <td class="text-right"><b>${{ number_format(collect($librocompras)->sum('sujeto_excluido'), 2) }}</b></td>
            </tr>
        </tfoot>
    </table>

</body>
</html>
