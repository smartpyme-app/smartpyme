<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura Comercial - {{ $venta->correlativo }}</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9pt;
            color: #000;
            margin: 0;
            padding: 0;
        }

        .container {
            margin: 0 auto;
            padding: 0cm;
            box-sizing: border-box;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
        }

        .header h1 {
            font-size: 32px;
            margin: 0;
            font-weight: bold;
        }

        .header p {
            font-size: 9pt;
            margin: 5px 0 0;
        }

        .title {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            margin: 10px 0 5px;
            text-transform: uppercase;
        }

        .subtitle {
            text-align: center;
            font-size: 12pt;
            margin: 0 0 20px;
            text-transform: uppercase;
        }

        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            vertical-align: top;
        }
        .info-grid td {
            vertical-align: top;
        }

        .label {
            background-color: #d0d0d0;
            color: #000;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9pt;
            padding: 2px 5px;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        table.items th, table.items td {
            border: 1px solid #000;
            padding: 3px;
            text-align: left;
            font-size: 9pt;
        }

        table.items th {
            background-color: #e0e0e0;
            font-weight: bold;
            text-align: center;
        }

        .origin {
            font-style: italic;
            margin: 5px 0 15px;
            text-align: right;
            font-size: 9pt;
        }

    </style>
</head>
<body>

<div class="container">

    <div class="header">
        <h1>JOZATO<span style="font-size: 8pt;">LLC</span></h1>
        <p>3411 Riverside Road Tatnal Building<br>#104, Wilmington, New Castle, DE 19810, USA</p>
    </div>

    <div class="title">COMMERCIAL INVOICE</div>
    <div class="subtitle">FACTURA COMERCIAL</div>

    <table class="info-grid">
        <tr>
            <td colspan="2">
                <div class="label">BUYER / COMPRADOR</div>
                <div style="padding: 2px 5px;">
                    {{ $cliente->nombre ? $cliente->nombre_completo : $cliente->nombre_empresa }}<br>
                    {{ $cliente->direccion ? $cliente->direccion : $cliente->empresa_direccion }}<br>
                    {{ $cliente->nit ? 'NIT: ' . $cliente->nit : '' }}<br>
                    {{ $cliente->ncr ? 'NRC: ' . $cliente->ncr : '' }}<br>
                    {{ $cliente->telefono ? 'Teléfono: ' . $cliente->telefono : '' }}
                </div>
            </td>
            <td>
                <div class="label">INVOICE / FACTURA</div>
                <div style="padding: 2px 5px;">{{ $venta->correlativo }}</div>
            </td>
            <td>
                <div class="label">DATE / FECHA</div>
                <div style="padding: 2px 5px;">{{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</div>
            </td>
        </tr>
        <tr>
            <td> <div class="label">PROFORMA</div> <div style="padding: 2px 5px;">{{ $venta->proforma ?? '—' }}</div> </td>
            <td> <div class="label">VÍA</div> <div style="padding: 2px 5px;">{{ $venta->via ?? '—' }}</div> </td>
            <td> <div class="label">PACKAGES / PAQUETES</div> <div style="padding: 2px 5px;">{{ $venta->detalles->sum('cantidad') }}</div> </td>
            <td> <div class="label">MARKS / MARCAS</div> <div style="padding: 2px 5px;">{{ $venta->marcas ?? 'N/M' }}</div> </td>
        </tr>
        <tr>
            <td colspan="2"> <div class="label">PAYMENT / PAGO</div> <div style="padding: 2px 5px;">{{ $venta->pago_descripcion ?? '—' }}</div> </td>
            <td colspan="2"> <div class="label">INCOTERM</div> <div style="padding: 2px 5px;">{{ $venta->incoterm ?? '—' }} - {{ \App\Models\MH\Recinto::where('cod', $venta->recinto_fiscal)->first()->nombre ?? '—' }}</div> </td>
    </table>


    <p>{{$venta->observaciones}}</p>

    <table class="items">
        <thead>
            <tr>
                <th style="text-align: center;">QUANTITY / CANTIDAD</th>
                <th>GOODS OF DESCRIPTION / DESCRIPCIÓN</th>
                <th style="text-align: right;">UNIT PRICE / P. UNITARIO</th>
                <th style="text-align: right;">AMOUNT / TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($venta->detalles as $detalle)
            <tr>
                <td style="text-align: center;">{{ $detalle->cantidad }}</td>
                <td>{{ $detalle->descripcion }}</td>
                <td style="text-align: right;">{{ $venta->empresa->currency->currency_symbol }}{{ number_format($detalle->precio, 2) }}</td>
                <td style="text-align: right;">{{ $venta->empresa->currency->currency_symbol }}{{ number_format($detalle->total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" style="text-align: center;">
                    <!-- Total -->
                </td>
                <td>Summary / Subtotal</td>
                <td style="text-align: right;">{{ $venta->empresa->currency->currency_symbol }}{{ number_format($venta->sub_total, 2) }}</td>
            </tr>
            <tr>
                <td colspan="2" style="text-align: center;">
                    <!-- {{ $venta->detalles->sum('cantidad') }} Cajas -->
                </td>
                <td>Tax Rate / Tasa del Impuesto</td>
                <td style="text-align: right;"></td>
            </tr>
            <tr>
                <td colspan="2"></td>
                <td>Taxes / Impuesto</td>
                <td style="text-align: right;">{{ $venta->empresa->currency->currency_symbol }}{{ number_format($venta->iva, 2) }}</td>
            </tr>
            <tr>
                <td colspan="2"></td>
                <td>Others / Otros</td>
                <td style="text-align: right;">-</td>
            </tr>
            <tr>
                <td colspan="2"></td>
                <td>Total USD</td>
                <td style="text-align: right;">{{ $venta->empresa->currency->currency_symbol }}{{ number_format($venta->total, 2) }}</td>
            </tr>
        </tfoot>
    </table>

</div>

</body>
</html>