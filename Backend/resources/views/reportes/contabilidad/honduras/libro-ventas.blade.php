<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Libro de Ventas - Honduras</title>
    <style>
        body{ font-family: Arial, sans-serif; font-size: 9px; }
        h1, h2{ margin: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 0.5rem; }
        thead th, tbody td { padding: 2px 4px; border: 1px solid #dee2e6; }
        thead th { font-weight: bold; background: #f1f3f5; }
        .text-center{text-align: center;}
        .text-right{text-align: right;}
    </style>
</head>
<body>
    @php $empresa = Auth::user()->empresa()->with('currency')->first(); $simbolo = ($empresa && $empresa->currency) ? $empresa->currency->currency_symbol : 'L'; @endphp

    <h1 class="text-center">LIBRO DE VENTAS - HONDURAS</h1>
    <h2 class="text-center">{{ Auth::user()->empresa()->pluck('nombre')->first() }}</h2>
    <p><b>Período:</b> {{ ucfirst(Carbon\Carbon::parse($request->inicio)->translatedFormat('F')) }} {{ Carbon\Carbon::parse($request->inicio)->format('Y') }}</p>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>No. de Orden de Compra Exenta</th>
                <th>Documento / DUA Exportación</th>
                <th>Documento de transferencias de bienes FYDUCA</th>
                <th>Notas de Crédito emitidas en el periodo</th>
                <th>Fecha de emisión de Factura a la que se aplica la nota de credito</th>
                <th>Número de Factura relacionada con la Nota de Crédito</th>
                <th>Cliente</th>
                <th>RTN del Cliente</th>
                <th>Descripción</th>
                <th>No. de Factura que respalda la venta</th>
                <th class="text-right">Importe Venta Exenta</th>
                <th class="text-right">Importe Venta Gravada</th>
                <th class="text-right">Importe Venta Exonerada</th>
                <th class="text-right">Impuesto Sobre Ventas</th>
                <th class="text-right">Importe Exportación</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($libroventas ?? [] as $row)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($row['fecha'])->format('d/m/Y') }}</td>
                    <td>{{ $row['num_orden_exenta'] ?? '' }}</td>
                    <td>{{ $row['documento_dua_exportacion'] ?? '' }}</td>
                    <td>{{ $row['documento_fyduca'] ?? '' }}</td>
                    <td>{{ $row['nota_credito_numero'] ?? '' }}</td>
                    <td>{{ isset($row['fecha_factura_relacionada']) && $row['fecha_factura_relacionada'] ? \Carbon\Carbon::parse($row['fecha_factura_relacionada'])->format('d/m/Y') : '' }}</td>
                    <td>{{ $row['numero_factura_relacionada'] ?? '' }}</td>
                    <td>{{ $row['cliente'] ?? '' }}</td>
                    <td>{{ $row['rtn'] ?? '' }}</td>
                    <td>{{ $row['descripcion'] ?? '' }}</td>
                    <td>{{ $row['no_factura'] ?? '' }}</td>
                    <td class="text-right">{{ $simbolo }}{{ number_format($row['importe_exenta'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ $simbolo }}{{ number_format($row['importe_gravada'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ $simbolo }}{{ number_format($row['importe_exonerada'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ $simbolo }}{{ number_format($row['impuesto_ventas'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ $simbolo }}{{ number_format($row['importe_exportacion'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
