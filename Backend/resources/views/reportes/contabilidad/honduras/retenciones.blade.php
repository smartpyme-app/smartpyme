<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de Retención</title>
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

    <h1 class="text-center">COMPROBANTE DE RETENCIÓN</h1>
    <h2 class="text-center">{{ Auth::user()->empresa()->pluck('nombre')->first() }}</h2>
    <p><b>Período:</b> {{ ucfirst(Carbon\Carbon::parse($request->inicio)->translatedFormat('F')) }} {{ Carbon\Carbon::parse($request->inicio)->format('Y') }}</p>

    <table>
        <thead>
            <tr>
                <th>Fecha de Comprobante de Retención</th>
                <th>Número de Comprobante de Retención</th>
                <th>Fecha de Factura</th>
                <th>Factura relacionada con Comprobante</th>
                <th>Nombre del Agente Retenedor</th>
                <th>Registro Tributario Nacional</th>
                <th class="text-right">Importe Base de Retención</th>
                <th class="text-right">Impuesto Retenido</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($libroretenciones ?? [] as $row)
                <tr>
                    <td>{{ isset($row['fecha_comprobante']) && $row['fecha_comprobante'] ? \Carbon\Carbon::parse($row['fecha_comprobante'])->format('d/m/Y') : '' }}</td>
                    <td>{{ $row['numero_comprobante'] ?? '' }}</td>
                    <td>{{ isset($row['fecha_factura']) && $row['fecha_factura'] ? \Carbon\Carbon::parse($row['fecha_factura'])->format('d/m/Y') : '' }}</td>
                    <td>{{ $row['factura_relacionada'] ?? '' }}</td>
                    <td>{{ $row['nombre_agente_retenedor'] ?? '' }}</td>
                    <td>{{ $row['registro_tributario_nacional'] ?? '' }}</td>
                    <td class="text-right">{{ $simbolo }}{{ number_format($row['importe_base_retencion'] ?? 0, 2) }}</td>
                    <td class="text-right">{{ $simbolo }}{{ number_format($row['impuesto_retenido'] ?? 0, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
