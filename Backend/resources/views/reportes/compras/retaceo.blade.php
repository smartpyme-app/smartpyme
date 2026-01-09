<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Retaceo #{{ $retaceo->codigo }} - {{ $retaceo->compra->nombre_proveedor ?? 'N/A' }}</title>
    <style>
        *{
            margin: 0cm;
            font-family: 'system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue","Noto Sans","Liberation Sans",Arial,sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji"';
        }
        body {
            font-family: serif;
            margin: 50px;
        }
        h1,h2,h3,h4,h5,h6{
            color: #005CBB !important;
        }

        table{
            width: 100%;
            border-collapse: collapse;
        }
        .table th, .table td{
            border: 0px;
            border-collapse: collapse;
            padding: 10px 5px;
            text-align: left;
        }
        .text-right{
            text-align: right !important;
        }
        .text-center{
            text-align: center !important;
        }
        .border-bottom{
            border-bottom: 1px solid #005CBB !important;
        }
        .border-top{
            border-top: 1px solid #005CBB !important;
        }
        .bg-light{
            background-color: #f8f9fa;
        }
        .fw-bold{
            font-weight: bold;
        }
    </style>
</head>
<body>
    <table>
        <tbody>
            <tr>
                <td>
                    <h1>{{ $retaceo->empresa->nombre ?? 'N/A' }}</h1>
                    <p>
                        {{ $retaceo->empresa->municipio ?? '' }}
                        {{ $retaceo->empresa->departamento ?? '' }}
                    </p>
                    <p>{{ $retaceo->empresa->direccion ?? '' }}</p>
                    <p>{{ $retaceo->empresa->telefono ?? '' }}</p>
                </td>
                <td class="text-right">
                    @if ($retaceo->empresa && $retaceo->empresa->logo)
                        <img width="150" height="150" src="{{ public_path('img/'.$retaceo->empresa->logo) }}" alt="Logo">
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    <br>

    <table>
        <tbody>
            <tr>
                <td><h2>RETACEO</h2></td>
                <td class="text-right">
                    <p><strong>Código:</strong> {{ $retaceo->codigo }}</p>
                    <p><strong>Fecha:</strong> {{ \Carbon\Carbon::parse($retaceo->fecha)->format('d/m/Y') }}</p>
                    <p><strong>Estado:</strong> {{ $retaceo->estado }}</p>
                </td>
            </tr>
        </tbody>
    </table>

    <br>

    <table>
        <tbody>
            <tr>
                <td><h4>Información de la Compra</h4></td>
            </tr>
            <tr>
                <td>
                    <p><strong>Proveedor:</strong> {{ $retaceo->compra->nombre_proveedor ?? 'N/A' }}</p>
                    <p><strong>Referencia:</strong> {{ $retaceo->compra->referencia ?? 'N/A' }}</p>
                    <p><strong>Número DUCA:</strong> {{ $retaceo->numero_duca ?? 'N/A' }}</p>
                    <p><strong>Número Factura:</strong> {{ $retaceo->numero_factura ?? 'N/A' }}</p>
                    <p><strong>Incoterm:</strong> {{ $retaceo->incoterm ?? 'N/A' }}</p>
                </td>
                <td>
                    <p><strong>Sucursal:</strong> {{ $retaceo->sucursal->nombre ?? 'N/A' }}</p>
                    <p><strong>Usuario:</strong> {{ $retaceo->usuario->name ?? 'N/A' }}</p>
                    <p><strong>Fecha de Elaboración:</strong> {{ \Carbon\Carbon::parse($retaceo->created_at)->format('d/m/Y H:i:s') }}</p>
                </td>
            </tr>
        </tbody>
    </table>

    <br>

    <h4>Gastos Asociados</h4>
    <table class="table">
        <thead>
            <tr>
                <th class="border-bottom">Tipo de Gasto</th>
                <th class="border-bottom">Concepto</th>
                <th class="border-bottom text-right">Monto</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalTransporte = 0;
                $totalSeguro = 0;
                $totalOtros = 0;
            @endphp
            @foreach($retaceo->gastos as $gasto)
                @php
                    if($gasto->tipo_gasto == 'Transporte') {
                        $totalTransporte += $gasto->monto;
                    } elseif($gasto->tipo_gasto == 'Seguro') {
                        $totalSeguro += $gasto->monto;
                    } else {
                        $totalOtros += $gasto->monto;
                    }
                @endphp
                <tr>
                    <td class="border-bottom">{{ $gasto->tipo_gasto }}</td>
                    <td class="border-bottom">{{ $gasto->gasto->concepto ?? 'N/A' }}</td>
                    <td class="border-bottom text-right">${{ number_format($gasto->monto, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" class="text-right fw-bold">Total Transporte:</td>
                <td class="text-right fw-bold">${{ number_format($totalTransporte, 2) }}</td>
            </tr>
            <tr>
                <td colspan="2" class="text-right fw-bold">Total Seguro:</td>
                <td class="text-right fw-bold">${{ number_format($totalSeguro, 2) }}</td>
            </tr>
            <tr>
                <td colspan="2" class="text-right fw-bold">Total Otros:</td>
                <td class="text-right fw-bold">${{ number_format($totalOtros, 2) }}</td>
            </tr>
            <tr>
                <td colspan="2" class="text-right fw-bold border-top">Total de Gastos:</td>
                <td class="text-right fw-bold border-top">${{ number_format($retaceo->total_gastos, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <br>

    <h4>Distribución de Gastos por Producto</h4>
    <table class="table">
        <thead>
            <tr>
                <th class="border-bottom">Producto</th>
                <th class="border-bottom text-center">Cantidad</th>
                <th class="border-bottom text-right">Costo Original</th>
                <th class="border-bottom text-right">Valor FOB</th>
                <th class="border-bottom text-right">Dist. %</th>
                <th class="border-bottom text-right">% DAI</th>
                <th class="border-bottom text-right">Transporte</th>
                <th class="border-bottom text-right">Seguro</th>
                <th class="border-bottom text-right">DAI</th>
                <th class="border-bottom text-right">Otros</th>
                <th class="border-bottom text-right">Landed</th>
                <th class="border-bottom text-right">Costo Final</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalCantidad = 0;
                $totalCostoOriginal = 0;
                $totalFOB = 0;
                $totalTransporte = 0;
                $totalSeguro = 0;
                $totalDAI = 0;
                $totalOtros = 0;
                $totalLanded = 0;
            @endphp
            @foreach($retaceo->distribucion as $item)
                @php
                    $totalCantidad += $item->cantidad;
                    $totalCostoOriginal += $item->costo_original * $item->cantidad;
                    $totalFOB += $item->valor_fob;
                    $totalTransporte += $item->monto_transporte;
                    $totalSeguro += $item->monto_seguro;
                    $totalDAI += $item->monto_dai;
                    $totalOtros += $item->monto_otros;
                    $totalLanded += $item->costo_landed;
                @endphp
                <tr>
                    <td class="border-bottom">{{ $item->producto->nombre ?? 'Producto sin nombre' }}</td>
                    <td class="border-bottom text-center">{{ number_format($item->cantidad, 0) }}</td>
                    <td class="border-bottom text-right">${{ number_format($item->costo_original, 2) }}</td>
                    <td class="border-bottom text-right">${{ number_format($item->valor_fob, 2) }}</td>
                    <td class="border-bottom text-right">{{ number_format($item->porcentaje_distribucion, 2) }}%</td>
                    <td class="border-bottom text-right">{{ number_format($item->porcentaje_dai ?? 0, 2) }}%</td>
                    <td class="border-bottom text-right">${{ number_format($item->monto_transporte, 2) }}</td>
                    <td class="border-bottom text-right">${{ number_format($item->monto_seguro, 2) }}</td>
                    <td class="border-bottom text-right">${{ number_format($item->monto_dai, 2) }}</td>
                    <td class="border-bottom text-right">${{ number_format($item->monto_otros, 2) }}</td>
                    <td class="border-bottom text-right">${{ number_format($item->costo_landed, 2) }}</td>
                    <td class="border-bottom text-right fw-bold">${{ number_format($item->costo_retaceado, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="bg-light">
                <td class="fw-bold border-top">TOTALES</td>
                <td class="text-center fw-bold border-top">{{ number_format($totalCantidad, 0) }}</td>
                <td class="text-right fw-bold border-top">${{ number_format($totalCostoOriginal, 2) }}</td>
                <td class="text-right fw-bold border-top">${{ number_format($totalFOB, 2) }}</td>
                <td class="text-right fw-bold border-top">100%</td>
                <td class="text-right fw-bold border-top">-</td>
                <td class="text-right fw-bold border-top">${{ number_format($totalTransporte, 2) }}</td>
                <td class="text-right fw-bold border-top">${{ number_format($totalSeguro, 2) }}</td>
                <td class="text-right fw-bold border-top">${{ number_format($totalDAI, 2) }}</td>
                <td class="text-right fw-bold border-top">${{ number_format($totalOtros, 2) }}</td>
                <td class="text-right fw-bold border-top">${{ number_format($totalLanded, 2) }}</td>
                <td class="text-right fw-bold border-top">${{ number_format($retaceo->total_retaceado, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <br>

    @if($retaceo->observaciones)
    <h4>Observaciones:</h4>
    <p>{{ $retaceo->observaciones }}</p>
    <br>
    @endif

    <table>
        <tbody>
            <tr>
                <td class="text-center">
                    <p><small>Documento generado el {{ \Carbon\Carbon::now()->format('d/m/Y H:i:s') }}</small></p>
                </td>
            </tr>
        </tbody>
    </table>

</body>
</html>
