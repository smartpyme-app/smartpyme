<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Libro de Compras - Honduras</title>
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

    <h1 class="text-center">LIBRO DE COMPRAS - HONDURAS</h1>
    <h2 class="text-center">{{ Auth::user()->empresa()->pluck('nombre')->first() }}</h2>
    <p><b>Período:</b> {{ ucfirst(Carbon\Carbon::parse($request->inicio)->translatedFormat('F')) }} {{ Carbon\Carbon::parse($request->inicio)->format('Y') }}</p>

    <table>
        <thead>
            <tr>
                <th>Fecha de Documento</th>
                <th>Fecha de Contabilización</th>
                <th>Documento / DUA Importación</th>
                <th>Documento de adquisiciones FYDUCA</th>
                <th>Proveedor</th>
                <th>Registro Tributario Nacional del proveedor</th>
                <th>Descripción de la compra</th>
                <th>No. de Factura de la compra</th>
                <th class="text-right">Importe Compra Exenta</th>
                <th class="text-right">Importe Compra Gravada</th>
                <th class="text-right">Impuesto Sobre Ventas</th>
                <th class="text-right">Importe Importación</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($librocompras ?? [] as $row)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($row['fecha_documento'])->format('d/m/Y') }}</td>
                    <td>{{ \Carbon\Carbon::parse($row['fecha_contabilizacion'])->format('d/m/Y') }}</td>
                    <td>{{ $row['documento_dua_importacion'] ?? '' }}</td>
                    <td>{{ $row['documento_fyduca'] ?? '' }}</td>
                    <td>{{ $row['proveedor'] ?? '' }}</td>
                    <td>{{ $row['rtn_proveedor'] ?? '' }}</td>
                    <td>{{ $row['descripcion_compra'] ?? '' }}</td>
                    <td>{{ $row['no_factura_compra'] ?? '' }}</td>
                    <td class="text-right">{{ $simbolo }}{{ number_format($row['importe_exenta'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ $simbolo }}{{ number_format($row['importe_gravada'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ $simbolo }}{{ number_format($row['impuesto_ventas'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ $simbolo }}{{ number_format($row['importe_importacion'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
