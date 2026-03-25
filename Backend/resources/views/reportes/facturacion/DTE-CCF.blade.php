<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $DTE['identificacion']['codigoGeneracion'] }} </title>
    <style>

        *{ 
            margin: 0cm; font-family: "Segoe UI",Roboto,"Helvetica Neue","Noto Sans","Liberation Sans",Arial,sans-serif;
        }
        body {
            font-family: serif; margin: 40px;
            font-size: 10px;
        }
        h1,h2,h3,h4,h5,h6{color: #003 !important; }

        .table{width: 100%; border-collapse: collapse; }
        .table th, .table td{
            border-collapse: collapse;
            padding: 2px 3px;
            text-align: left;
        }

        .table.bordered th, .table.bordered td{
            border: 1px solid #aaa;
        }

        /* Propiedades específicas para DomPDF - división de tabla entre páginas */
        .table.bordered {
            page-break-inside: auto;
            page-break-before: auto;
            page-break-after: auto;
        }
        
        /* Permitir división de filas en DomPDF */
        .table.bordered tbody tr {
            page-break-inside: auto;
            page-break-before: auto;
            page-break-after: auto;
        }
        
        /* Mantener encabezado en cada página */
        .table.bordered thead {
            display: table-header-group;
        }
        
        .table.bordered thead tr {
            page-break-inside: avoid;
            page-break-after: avoid;
        }
        
        /* Mantener pie de página al final */
        .table.bordered tfoot {
            display: table-footer-group;
        }
        
        .table.bordered tfoot tr {
            page-break-inside: avoid;
            page-break-before: avoid;
        }
        
        /* Evitar división de celdas individuales */
        .table.bordered td, .table.bordered th {
            page-break-inside: avoid;
            vertical-align: top;
        }
        
        /* Solución específica para DomPDF - forzar división de tabla */
        .table-products {
            page-break-inside: auto !important;
        }
        
        .table-products tbody {
            page-break-inside: auto !important;
        }
        
        .table-products tbody tr {
            page-break-inside: auto !important;
            page-break-before: auto !important;
            page-break-after: auto !important;
        }
        
        /* Forzar que DomPDF divida la tabla */
        .force-page-break {
            page-break-before: always;
        }
        
        /* Solución alternativa para DomPDF - usar overflow */
        .table-products {
            table-layout: fixed;
            width: 100%;
        }
        
        /* Asegurar que DomPDF respete las reglas de página */
        @media print {
            .table-products {
                page-break-inside: auto !important;
            }
            .table-products tbody tr {
                page-break-inside: auto !important;
            }
        }
        
        /* Solución final para DomPDF - remover todas las restricciones de page-break */
        .table-products,
        .table-products tbody,
        .table-products tbody tr {
            page-break-inside: auto !important;
            page-break-before: auto !important;
            page-break-after: auto !important;
        }
        
        /* Solo mantener restricciones en encabezado y pie */
        .table-products thead tr {
            page-break-inside: avoid !important;
            page-break-after: avoid !important;
        }
        
        .table-products tfoot tr {
            page-break-inside: avoid !important;
            page-break-before: avoid !important;
        }
        .text-right{
            text-align: right !important;
        }

        .bg-light{
            background-color: #ddd;
        }

    </style>
    
</head>
<body>
    
    @php
    $tipoModelo = [
        'Modelo Facturación previo',
        'Modelo Facturación diferido'
        ];
        $tipoOperacion = [
            'Transmisión normal',
            'Transmisión por contingencia'
            ];
    @endphp
    @php
        $tipoDocumento = [
                '36',
                '13'
        ];
    @endphp
            <table class="table">
            <tbody>
                <tr>
                    <td  style="width: 25%;">
                        {{-- Logo --}}
                        @if ($registro->empresa()->pluck('logo')->first())
                            <img height="100" src="{{ asset('img/'.$registro->empresa()->pluck('logo')->first()) }}" alt="Logo">
                        @endif
                    </td>
                    <td style="width: 50%; text-align: center;">
                        <h2>DOCUMENTO TRIBUTARIO ELECTRÓNICO</h2>
                        <h2>COMPROBANTE DE CRÉDITO FISCAL</h2>
                    </td>
                    <td style="width: 25%; text-align: right;">
                        {!! '<img id="qrcode" width="100" height="100" src="data:image/png;base64,' . DNS2D::getBarcodePNG($registro->qr, 'QRCODE', 10, 10, array(0,0,0), true) . '" alt="barcode"   />' !!}
                    </td>
                </tr>
            </tbody>
        </table>
        <table class="table bordered">
            <tbody>
                <tr>
                    <td style="width: 50%;">
                        <p><b>Código de Generación:</b> {{ $DTE['identificacion']['codigoGeneracion'] }}</p>
                        <p><b>Número de Control:</b> {{ $DTE['identificacion']['numeroControl'] }}</p>
                        <p><b>Sello de Recepción:</b> {{ $DTE['sello'] }}</p>
                    </td>
                    <td style="width: 50%;">
                        <p><b>Modelo de Facturación:</b> {{ $tipoModelo[$DTE['identificacion']['tipoModelo'] - 1] }}</p>
                        <p><b>Tipo de Transmisión:</b> {{ $tipoOperacion[$DTE['identificacion']['tipoOperacion'] - 1] }}</p>
                        <p><b>Fecha y Hora de Generación:</b> {{ \Carbon\Carbon::parse($DTE['identificacion']['fecEmi'] . ' ' . $DTE['identificacion']['horEmi'])->format('d/m/Y H:i:s') }}</p>
                    </td>
                </tr>
            </tbody>
        </table>
        <table class="table bordered">
            <tbody>
                <tr>
                    <td class="bg-light" style="width: 50%;">
                        <h3>Emisor</h3>
                    </td>
                    <td class="bg-light" style="width: 50%;">
                        <h3>Receptor</h3>
                    </td>
                </tr>
                <tr>
                    <td style="width: 50%; vertical-align: top;">
                        <p><b>Nombre o razón social: </b>{{ $DTE['emisor']['nombre'] }}</p>
                        <p><b>NIT:</b> {{ $DTE['emisor']['nit'] }} <b> &nbsp;&nbsp;&nbsp; NRC:</b> {{ $DTE['emisor']['nrc'] }}</p>
                        <p><b>Act. económica:</b> {{ $DTE['emisor']['descActividad'] }}</p>
                        <p><b>Dirección:</b> {{ $DTE['emisor']['direccion']['complemento'] }}
                            {{ $registro->empresa()->pluck('municipio')->first(); }}
                            {{ $registro->empresa()->pluck('departamento')->first(); }}
                        </p>
                        
                        <p><b>Teléfono: </b>{{ $DTE['emisor']['telefono'] }}</p>
                        <p><b>Correo: </b>{{ $DTE['emisor']['correo'] }}</p>
                    </td>
                    <td style="width: 50%; vertical-align: top;">
                        <p><b>Nombre o razón social: </b>{{ $DTE['receptor']['nombre'] }}</p>
                        <p><b>NIT:</b> {{ $DTE['receptor']['nit'] }} <b> &nbsp;&nbsp;&nbsp; NRC:</b> {{ $DTE['receptor']['nrc'] }}</p>
                        <p><b>Act. económica:</b> {{ $DTE['receptor']['descActividad'] }}</p>
                        <p><b>Dirección:</b> {{ $DTE['receptor']['direccion']['complemento'] }}
                            {{ $registro->cliente()->pluck('municipio')->first(); }}
                            {{ $registro->cliente()->pluck('departamento')->first(); }}
                        </p>
                        <p><b>Teléfono: </b>{{ $DTE['receptor']['telefono'] }}</p>
                        <p><b>Correo: </b>{{ $DTE['receptor']['correo'] }}</p>
                    </td>
                </tr>
            </tbody>
        </table>
        <table class="table bordered table-products">
            <thead>
                <tr class="bg-light">
                    <th width="3%" class="border-bottom">N°</th>
                    <th width="5%" class="border-bottom">Cantidad</th>
                    <th width="11%" class="border-bottom">Código</th>
                    <th class="border-bottom">Descripción</th>
                    <th width="10%" class="border-bottom text-right">Precio Unitario</th>
                    <th width="10%" class="border-bottom text-right">Descuento por ítem</th>
                    @if ($DTE['resumen']['totalNoGravado'] > 0)
                        <th width="10%" class="border-bottom text-right">Otros Montos no Afectos</th>
                    @endif
                    <th width="10%" class="border-bottom text-right">Ventas No Sujetas</th>
                    <th width="10%" class="border-bottom text-right">Ventas Exentas</th>
                    <th width="10%" class="border-bottom text-right">Ventas Gravadas</th>
                </tr>
            </thead>
            <tbody>
                @foreach($DTE['cuerpoDocumento'] as $detalle)
                <tr>
                    <td class="border-bottom">   {{ $detalle['numItem']  }}</td>
                    <td class="border-bottom">   {{ number_format($detalle['cantidad'] , 2) }}</td>
                    <td class="border-bottom">   {{ $detalle['codigo']  }}</td>
                    <td class="border-bottom">
                        {{ $detalle['descripcion']  }}
                        @if ($registro->empresa()->pluck('id')->first() != 529 && $registro->detalles->where('descripcion', $detalle['descripcion'])->first() && $registro->detalles->where('descripcion', $detalle['descripcion'])->first()->producto)
                            <br>
                            <span class="text-muted">
                                {!! nl2br(e($registro->detalles->where('descripcion', $detalle['descripcion'])->first()->producto->descripcion)) !!}
                            </span>
                        @endif
                    </td>
                    <td class="border-bottom text-right">   ${{number_format($detalle['precioUni'] , 2) }}</td>
                    <td class="border-bottom text-right">   ${{number_format($detalle['montoDescu'] , 2) }}</td>
                    @if ($detalle['noGravado'])
                        <td class="border-bottom text-right">   ${{ number_format($detalle['noGravado'], 2) }}</th>
                    @endif
                    <td class="border-bottom text-right">   ${{ number_format($detalle['ventaNoSuj'], 2) }}</th>
                    <td class="border-bottom text-right">   ${{ number_format($detalle['ventaExenta'], 2) }}</th>
                    <td class="border-bottom text-right">   ${{ number_format($detalle['ventaGravada'], 2) }}</th>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4"></td>
                    <td class="bg-light" colspan="2"><b>Suma de ventas:</b> </td>
                    @if ($DTE['resumen']['totalNoGravado'] > 0)
                        <td class="bg-light text-right">${{ number_format($DTE['resumen']['totalNoGravado'], 2) }}</td>
                    @endif
                    <td class="bg-light text-right">${{ number_format($DTE['resumen']['totalNoSuj'], 2) }}</td>
                    <td class="bg-light text-right">${{ number_format($DTE['resumen']['totalExenta'], 2) }}</td>
                    <td class="bg-light text-right">${{ number_format($DTE['resumen']['totalGravada'], 2) }}</td>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td colspan="{{ $DTE['resumen']['totalNoGravado'] > 0 ? 5 : 4 }}">Suma total de operaciones: </td>
                    <td class="text-right">${{ number_format($DTE['resumen']['subTotalVentas'], 2) }}</td>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td colspan="{{ $DTE['resumen']['totalNoGravado'] > 0 ? 5 : 4 }}">Monto global Desc., Rebajas y otros a ventas no sujetas: </td>
                    <td class="text-right">${{ number_format($DTE['resumen']['descuNoSuj'], 2) }}</td>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td colspan="{{ $DTE['resumen']['totalNoGravado'] > 0 ? 5 : 4 }}">Monto global Desc., Rebajas y otros a ventas exentas: </td>
                    <td class="text-right">${{ number_format($DTE['resumen']['descuExenta'], 2) }}</td>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td colspan="{{ $DTE['resumen']['totalNoGravado'] > 0 ? 5 : 4 }}">Monto global Desc., Rebajas y otros a ventas gravadas: </td>
                    <td class="text-right">${{ number_format($DTE['resumen']['descuGravada'], 2) }}</td>
                </tr>

                @if (isset($DTE['resumen']['tributos']))
                    @foreach ($DTE['resumen']['tributos'] as $tributo)
                    <tr>
                        <td colspan="4"></td>
                        <td colspan="{{ $DTE['resumen']['totalNoGravado'] > 0 ? 5 : 4 }}">{{ $tributo['descripcion'] }}: </td>
                        <td class="text-right">${{ number_format($tributo['valor'], 2) }}</td>
                    </tr>
                    @endforeach
                @endif
                <tr>
                    <td colspan="4"></td>
                    <td colspan="{{ $DTE['resumen']['totalNoGravado'] > 0 ? 5 : 4 }}">Sub-Total: </td>
                    <td class="text-right">${{ number_format($DTE['resumen']['subTotal'], 2) }}</td>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td colspan="{{ $DTE['resumen']['totalNoGravado'] > 0 ? 5 : 4 }}">IVA Percibido: (+) </td>
                    <td class="text-right">${{ number_format(0, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td colspan="{{ $DTE['resumen']['totalNoGravado'] > 0 ? 5 : 4 }}">IVA Retenido: (-) </td>
                    <td class="text-right">${{ number_format($DTE['resumen']['ivaRete1'], 2) }}</td>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td colspan="{{ $DTE['resumen']['totalNoGravado'] > 0 ? 5 : 4 }}">Retención de Renta: </td>
                    <td class="text-right">${{ number_format($DTE['resumen']['reteRenta'], 2) }}</td>
                </tr>
                @if(isset($registro->propina) && floatval($registro->propina) > 0)
                    <tr>
                        <td colspan="4"></td>
                        <td colspan="{{ $DTE['resumen']['totalNoGravado'] > 0 ? 5 : 4 }}">Propina: </td>
                        <td class="text-right">${{ number_format(floatval($registro->propina), 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="4"></td>
                        <td colspan="{{ $DTE['resumen']['totalNoGravado'] > 0 ? 5 : 4 }}">Total: </td>
                        <td class="text-right">${{ number_format(floatval($DTE['resumen']['montoTotalOperacion'] ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="4"></td>
                        <td colspan="{{ $DTE['resumen']['totalNoGravado'] > 0 ? 5 : 4 }}"><b>Total + Propina: </b></td>
                        <td class="text-right"><b>${{ number_format(floatval($DTE['resumen']['montoTotalOperacion'] ?? 0) + floatval($registro->propina), 2) }}</b></td>
                    </tr>
                @else
                    <tr>
                        <td colspan="4"></td>
                        <td colspan="{{ $DTE['resumen']['totalNoGravado'] > 0 ? 5 : 4 }}">Monto Total de la Operación: </td>
                        <td class="text-right">${{ number_format(floatval($DTE['resumen']['montoTotalOperacion'] ?? 0), 2) }}</td>
                    </tr>
                @endif
                @if ($DTE['resumen']['totalNoGravado'] > 0)
                    <tr>
                        <td colspan="4"></td>
                        <td colspan="{{ $DTE['resumen']['totalNoGravado'] > 0 ? 5 : 4 }}">Total Otros montos no afectos: </td>
                        <td class="text-right">${{ number_format($DTE['resumen']['totalNoGravado'], 2) }}</td>
                    </tr>
                @endif
                <tr>
                    <td colspan="4"></td>
                    <td class="bg-light" colspan="{{ $DTE['resumen']['totalNoGravado'] > 0 ? 5 : 4 }}"><b>Total a pagar:</b></td>
                    <td class="bg-light text-right"><b>${{ number_format(floatval($DTE['resumen']['totalPagar'] ?? 0) + (isset($registro->propina) && floatval($registro->propina) > 0 ? floatval($registro->propina) : 0), 2) }}</b></td>
                </tr>
            </tfoot>
        </table>

        <br>

        <table class="table bordered">
            <tbody>
                <tr>
                    <td width="50%"><b>Valor en Letras:</b> {{ $DTE['resumen']['totalLetras'] }}</td>
                    <td width="50%">
                        <b>Condición de la operación: </b>
                        @if ($DTE['resumen']['condicionOperacion'] == 2)
                            Crédito {{ \Carbon\Carbon::parse($registro->fecha)->diffInDays(\Carbon\Carbon::parse($registro->fecha_pago), false) }} días
                        @else
                            Contado
                        @endif
                    </td>
                </tr>
                @if (isset($DTE['apendice']))
                    @foreach ($DTE['apendice'] as $atributo)
                    <tr>
                        <td colspan="2">
                            <b>{{ $atributo['etiqueta'] }}:</b>
                            {{ $atributo['valor'] }}
                        </td>
                    </tr>
                    @endforeach
                @endif
            </tbody>
        </table>

</body>
</html>
