<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $DTE['identificacion']['codigoGeneracion'] }}</title>
    <style>
        * { margin: 0cm; font-family: "Segoe UI",Roboto,"Helvetica Neue","Noto Sans","Liberation Sans",Arial,sans-serif; }
        body { margin: 50px; font-size: 10px; }
        h1,h2,h3,h4,h5,h6 { color: #003 !important; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { border-collapse: collapse; padding: 3px; text-align: left; }
        .table.bordered th, .table.bordered td { border: 1px solid #aaa; }
        .table.bordered tr { page-break-inside: avoid; }
        .table.bordered thead { display: table-header-group; }
        .text-right { text-align: right !important; }
        .bg-light { background-color: #ddd; }
    </style>
</head>
<body>
    @php
        $tipoModelo = ['Modelo Facturación previo', 'Modelo Facturación diferido'];
        $tipoOperacion = ['Transmisión normal', 'Transmisión por contingencia'];
        $tiposDocumento = ['36' => 'NIT', '13' => 'DUI', '03' => 'Pasaporte', '02' => 'Carnet de residente', '37' => 'Otro'];
        $tiposDte = ['01' => 'Factura', '11' => 'Factura de exportación', '14' => 'Factura de sujeto excluido'];
        $documento = $DTE['documento'] ?? [];
        $emisor = $DTE['emisor'];
        $resumen = $DTE['resumen'];
        // Retorno sobre sujeto excluido (14): los ítems informan "compra" en lugar de ventas.
        $esCompra = ($DTE['documentoRelacionado'][0]['tipoDocumento'] ?? null) === '14';
    @endphp
    <table class="table">
        <tbody>
            <tr>
                <td style="width: 25%;">
                    @if ($registro->empresa()->pluck('logo')->first())
                        <img height="130" src="{{ asset('img/'.$registro->empresa()->pluck('logo')->first()) }}" alt="Logo">
                    @endif
                </td>
                <td style="width: 50%; text-align: center;">
                    <h2>DOCUMENTO TRIBUTARIO ELECTRÓNICO</h2>
                    <h2>EVENTO DE RETORNO</h2>
                </td>
                <td style="width: 25%; text-align: right;">
                    {!! '<img width="130" height="130" src="data:image/png;base64,' . DNS2D::getBarcodePNG($registro->qr, 'QRCODE', 10, 10, array(0,0,0), true) . '" alt="QR" />' !!}
                </td>
            </tr>
        </tbody>
    </table>
    <table class="table bordered">
        <tbody>
            <tr>
                <td style="width: 50%;">
                    <p><b>Código de Generación:</b> {{ $DTE['identificacion']['codigoGeneracion'] }}</p>
                    <p><b>Sello de Recepción:</b> {{ $DTE['sello'] ?? '' }}</p>
                </td>
                <td style="width: 50%;">
                    <p><b>Modelo de Facturación:</b> {{ $tipoModelo[$DTE['identificacion']['tipoModelo'] - 1] ?? '' }}</p>
                    <p><b>Tipo de Transmisión:</b> {{ $tipoOperacion[$DTE['identificacion']['tipoOperacion'] - 1] ?? '' }}</p>
                    <p><b>Fecha y Hora del evento:</b> {{ \Carbon\Carbon::parse($DTE['identificacion']['fecEmi'] . ' ' . $DTE['identificacion']['horEmi'])->format('d/m/Y H:i:s') }}</p>
                </td>
            </tr>
        </tbody>
    </table>
    <br>
    <table class="table bordered">
        <tbody>
            <tr>
                <td class="bg-light" style="width: 50%;"><h3>Emisor</h3></td>
                <td class="bg-light" style="width: 50%;"><h3>Receptor</h3></td>
            </tr>
            <tr>
                <td style="vertical-align: top;">
                    <p><b>Nombre o razón social:</b> {{ $emisor['nombre'] }}</p>
                    <p><b>NIT:</b> {{ $emisor['nit'] }}</p>
                    @if (!empty($emisor['recintoFiscal']))
                        <p><b>Recinto Fiscal:</b> {{ $emisor['recintoFiscal'] }}</p>
                        <p><b>Tipo de Régimen:</b> {{ $emisor['tipoRegimen'] }}</p>
                        <p><b>Régimen de Exportación:</b> {{ $emisor['regimen'] }}</p>
                    @endif
                </td>
                <td style="vertical-align: top;">
                    <p><b>Nombre:</b> {{ $documento['nombre'] ?? '' }}</p>
                    <p><b>{{ $tiposDocumento[$documento['tipoDocumento'] ?? ''] ?? 'Documento' }}:</b> {{ $documento['numDocumento'] ?? '' }}</p>
                    @if (!empty($documento['nombrePais']))
                        <p><b>País destino de la exportación:</b> {{ $documento['nombrePais'] }}</p>
                    @endif
                    <p><b>Teléfono:</b> {{ $documento['telefono'] ?? '' }}</p>
                    <p><b>Correo:</b> {{ $documento['correo'] ?? '' }}</p>
                </td>
            </tr>
        </tbody>
    </table>
    <br>
    <table class="table bordered">
        <thead>
            <tr class="bg-light">
                <th>Documentos relacionados</th>
                <th>Código de generación</th>
                <th>Fecha del documento</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($DTE['documentoRelacionado'] as $rel)
            <tr>
                <td>{{ $tiposDte[$rel['tipoDocumento']] ?? $rel['tipoDocumento'] }}</td>
                <td>{{ $rel['codigoGeneracion'] }}</td>
                <td>{{ \Carbon\Carbon::parse($rel['fechaEmision'])->format('d/m/Y') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <br>
    <table class="table bordered">
        <thead>
            <tr class="bg-light">
                <th width="3%">N°</th>
                <th width="6%">Cantidad</th>
                <th width="8%">Código</th>
                <th>Descripción</th>
                <th width="10%" class="text-right">Precio Unitario</th>
                <th width="10%" class="text-right">Descuento por ítem</th>
                @if ($esCompra)
                    <th width="10%" class="text-right"></th>
                    <th width="10%" class="text-right">Compra</th>
                @else
                    <th width="10%" class="text-right">Ventas Exentas</th>
                    <th width="10%" class="text-right">Ventas Gravadas</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($DTE['cuerpoDocumento'] as $detalle)
            <tr>
                <td>{{ $detalle['numItem'] }}</td>
                <td>{{ number_format($detalle['cantidad'], 2) }}</td>
                <td>{{ $detalle['codigo'] ?? '' }}</td>
                <td>{{ $detalle['descripcion'] }}</td>
                <td class="text-right">${{ number_format($detalle['precioUni'], 2) }}</td>
                <td class="text-right">${{ number_format($detalle['montoDescu'], 2) }}</td>
                @if ($esCompra)
                    <td></td>
                    <td class="text-right">${{ number_format($detalle['compra'], 2) }}</td>
                @else
                    <td class="text-right">${{ number_format($detalle['ventaExenta'], 2) }}</td>
                    <td class="text-right">${{ number_format($detalle['ventaGravada'], 2) }}</td>
                @endif
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                @if ($esCompra)
                    <td colspan="7" class="bg-light"><b>Total compra:</b></td>
                    <td class="bg-light text-right">${{ number_format($resumen['totalCompraExcluidos'], 2) }}</td>
                @else
                    <td colspan="6" class="bg-light"><b>Suma de ventas:</b></td>
                    <td class="bg-light text-right">${{ number_format($resumen['totalExenta'], 2) }}</td>
                    <td class="bg-light text-right">${{ number_format($resumen['totalGravada'], 2) }}</td>
                @endif
            </tr>
            <tr>
                <td colspan="7">Monto Total de la Operación:</td>
                <td class="text-right">${{ number_format($resumen['montoTotalOperacion'], 2) }}</td>
            </tr>
            @if ($resumen['totalIva'] > 0)
            <tr>
                <td colspan="7">IVA incluido:</td>
                <td class="text-right">${{ number_format($resumen['totalIva'], 2) }}</td>
            </tr>
            @endif
            <tr>
                <td colspan="7">IVA Retenido: (-)</td>
                <td class="text-right">${{ number_format($resumen['ivaRete'], 2) }}</td>
            </tr>
            <tr>
                <td colspan="7">Retención de Renta: (-)</td>
                <td class="text-right">${{ number_format($resumen['reteRenta'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td colspan="7" class="bg-light"><b>Total a pagar:</b></td>
                <td class="bg-light text-right"><b>${{ number_format($resumen['totalPagar'], 2) }}</b></td>
            </tr>
        </tfoot>
    </table>
    <table class="table bordered">
        <tbody>
            <tr><td><b>Valor en Letras:</b> {{ $resumen['totalLetras'] }}</td></tr>
        </tbody>
    </table>
</body>
</html>
