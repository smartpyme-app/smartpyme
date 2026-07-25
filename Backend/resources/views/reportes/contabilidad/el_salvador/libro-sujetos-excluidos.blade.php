<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Libro Compras Sujetos Excluidos</title>

    <style>
        body{ font-family: Arial, sans-serif; font-size: 7px; }
        h1, h2{ margin: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        thead th, tbody td { padding: 2px; text-align: left; border: 1px solid #dee2e6; }
        tfoot td { padding: 2px; text-align: left; border: 1px solid #dee2e6; background-color: #f8f9fa; font-weight: bold; }
        td, th { vertical-align: middle; word-wrap: break-word; }
        .text-center{text-align: center; }
        .text-right{text-align: right; }
    </style>
</head>
<body>
    @php $empresa = Auth::user()->empresa()->with('currency')->first(); $simbolo_moneda = \App\Helpers\CurrencyHelper::symbol($empresa); @endphp

    <h1 class="text-center">LIBRO DE COMPRAS A SUJETOS EXCLUIDOS</h1>
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
                <th>TIPO DE DOCUMENTO</th>
                <th>NUMERO DE NIT, DUI, OTRO DOCUMENTO</th>
                <th>NOMBRE, RAZÓN SOCIAL O DENOMINACIÓN</th>
                <th>FECHA DE EMISIÓN DEL DOCUMENTO</th>
                <th>SELLO RECEPCIÓN MH (DTE)</th>
                <th>CÓDIGO DE GENERACIÓN (DTE)</th>
                <th>REFERENCIA / NÚM. CONTROL</th>
                <th class="text-right">MONTO DE LA OPERACIÓN</th>
                <th class="text-right">MONTO DE LA RETENCIÓN IVA 13%</th>
                <th class="text-right">RETENCIÓN RENTA (PAGO A CUENTA)</th>
                <th>TIPO DE OPERACIÓN</th>
                <th>CLASIFICACIÓN</th>
                <th>SECTOR</th>
                <th>TIPO DE COSTO / GASTO</th>
                <th>NUMERO DE ANEXO</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($libroSujetoExcluido as $registro)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $registro['tipo_documento'] }}</td>
                    <td>{{ $registro['num_documento'] }}</td>
                    <td>{{ $registro['proveedor'] }}</td>
                    <td>{{ Carbon\Carbon::parse($registro['fecha'])->format('d/m/Y') }}</td>
                    <td>{{ $registro['sello_mh'] }}</td>
                    <td>{{ $registro['codigo_generacion'] }}</td>
                    <td>{{ $registro['referencia'] }}</td>
                    <td class="text-right">{{ $simbolo_moneda }}{{ number_format($registro['total'], 2) }}</td>
                    <td class="text-right">{{ $simbolo_moneda }}{{ number_format($registro['iva'], 2) }}</td>
                    <td class="text-right">{{ $simbolo_moneda }}{{ number_format($registro['renta_retenida'], 2) }}</td>
                    <td>{{ $registro['tipo_operacion'] }}</td>
                    <td>{{ $registro['clasificacion'] }}</td>
                    <td>{{ $registro['sector'] }}</td>
                    <td>{{ $registro['tipo'] }}</td>
                    <td>{{ $registro['num_anexo'] }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="8"><b>TOTALES</b></td>
                <td class="text-right"><b>{{ $simbolo_moneda }}{{ number_format(collect($libroSujetoExcluido)->sum('total'), 2) }}</b></td>
                <td class="text-right"><b>{{ $simbolo_moneda }}{{ number_format(collect($libroSujetoExcluido)->sum('iva'), 2) }}</b></td>
                <td class="text-right"><b>{{ $simbolo_moneda }}{{ number_format(collect($libroSujetoExcluido)->sum('renta_retenida'), 2) }}</b></td>
                <td colspan="5"></td>
            </tr>
        </tfoot>
    </table>

</body>
</html>
