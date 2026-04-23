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
            font-family: serif; 
            margin: 50px;
            font-size: 10px;
        }
        h1,h2,h3,h4,h5,h6{color: #003 !important; }

        .table{width: 100%; border-collapse: collapse; }
        .table th, .table td{
            border-collapse: collapse;
            padding: 3px;
            text-align: left;
        }

        .table.bordered th, .table.bordered td{
            border: 1px solid #aaa;
        }

        table.table.bordered.dte-detalle {
            table-layout: fixed;
            width: 100%;
        }
        table.table.bordered.dte-detalle th,
        table.table.bordered.dte-detalle td {
            word-wrap: break-word;
            overflow-wrap: break-word;
            vertical-align: top;
        }

        /* Propiedades para permitir división de tabla entre páginas */
        .table.bordered {
            page-break-inside: auto;
        }
        
        .table.bordered tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        
        .table.bordered thead {
            display: table-header-group;
        }
        
        .table.bordered tfoot {
            display: table-footer-group;
        }
        
        .text-right{
            text-align: right !important;
        }

        .bg-light{
            background-color: #ddd;
        }

        /* Sin position:fixed para evitar que logo/QR se superpongan con la tabla en DomPDF */

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
    <div class="dte-header">
        <table class="table">
            <tbody>
                <tr>
                    <td  style="width: 25%;">
                        {{-- Logo --}}
                        @if ($registro->empresa()->pluck('logo')->first())
                            <img height="130" src="{{ asset('img/'.$registro->empresa()->pluck('logo')->first()) }}" alt="Logo">
                        @endif
                    </td>
                    <td style="width: 50%; text-align: center;">
                        <h2>DOCUMENTO TRIBUTARIO ELECTRÓNICO</h2>
                        <h2>FACTURA</h2>
                    </td>
                    <td style="width: 25%; text-align: right;">
                        {!! '<img id="qrcode" width="130" height="130" src="data:image/png;base64,' . DNS2D::getBarcodePNG($registro->qr, 'QRCODE', 10, 10, array(0,0,0), true) . '" alt="barcode"   />' !!}
                    </td>
                </tr>
            </tbody>
        </table>
        <table class="table bordered">
            <tbody>
                <tr>
                    <td style="width: 50%;">
                        <p><b>Código de Generación:</b> {{ isset($DTE['identificacion']['codigoGeneracion']) ? $DTE['identificacion']['codigoGeneracion'] : '' }}</p>
                        <p><b>Número de Control:</b> {{ isset($DTE['identificacion']['numeroControl']) ? $DTE['identificacion']['numeroControl'] : '' }}</p>
                        <p><b>Sello de Recepción:</b> {{ isset($DTE['sello']) ? $DTE['sello'] : '' }}</p>
                    </td>
                    <td style="width: 50%;">
                        <p><b>Modelo de Facturación:</b> {{ isset($DTE['identificacion']['tipoModelo']) ? $tipoModelo[$DTE['identificacion']['tipoModelo'] - 1] : '' }}</p>
                        <p><b>Tipo de Transmisión:</b> {{ isset($DTE['identificacion']['tipoOperacion']) ? $tipoOperacion[$DTE['identificacion']['tipoOperacion'] - 1] : '' }}</p>
                        <p><b>Fecha y Hora de Generación:</b> 
                            @if (isset($DTE['identificacion']['fecEmi']) && isset($DTE['identificacion']['horEmi']))
                                {{ \Carbon\Carbon::parse($DTE['identificacion']['fecEmi'] . ' ' . $DTE['identificacion']['horEmi'])->format('d/m/Y H:i:s') }}
                            @endif
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <br>

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
                    <p><b>NIT:</b> {{ $DTE['emisor']['nit'] }}</p>
                    <p><b>NRC:</b> {{ $DTE['emisor']['nrc'] }}</p>
                    <p><b>Act. económica:</b> {{ $DTE['emisor']['descActividad'] }}</p>
                    <p><b>Dirección:</b> 
                        @if (isset($DTE['emisor']['direccion']['complemento']))
                            {{ $DTE['emisor']['direccion']['complemento'] }}
                        @endif
                        {{ $registro->empresa()->pluck('municipio')->first(); }}
                        {{ $registro->empresa()->pluck('departamento')->first(); }}
                    </p>
                    
                    <p><b>Teléfono: </b>{{ $DTE['emisor']['telefono'] }}</p>
                    <p><b>Correo: </b>{{ $DTE['emisor']['correo'] }}</p>
                </td>
                <td style="width: 50%; vertical-align: top;">
                    @if ($DTE['receptor'])
                        <p><b>Nombre o razón social: </b>{{ $DTE['receptor']['nombre'] }}</p>
                        <p><b>Tipo de Documento:</b>
                            @if ($DTE['receptor']['tipoDocumento'] == '36')
                                NIT
                            @endif
                            @if ($DTE['receptor']['tipoDocumento'] == '13')
                                DUI
                            @endif
                            @if ($DTE['receptor']['tipoDocumento'] == '03')
                                Pasaporte
                            @endif
                            @if ($DTE['receptor']['tipoDocumento'] == '02')
                                Carnet de residente
                            @endif
                            @if ($DTE['receptor']['tipoDocumento'] == '37')
                                Otro
                            @endif
                        </p>
                        <p><b>Núm de Documento:</b> {{ $DTE['receptor']['numDocumento'] }}</p>
                        <p><b>Act. económica:</b> {{ $DTE['receptor']['descActividad'] }}</p>
                            <p><b>Dirección:</b> 
                                @if ($registro->id_cliente)
                                    @if (isset($DTE['receptor']['direccion']['complemento']))
                                        {{ $DTE['receptor']['direccion']['complemento'] }}
                                    @endif
                                    {{ $registro->cliente()->pluck('municipio')->first(); }}
                                    {{ $registro->cliente()->pluck('departamento')->first(); }}
                                @endif
                            </p>
                        <p><b>Teléfono: </b>{{ $DTE['receptor']['telefono'] }}</p>
                        <p><b>Correo: </b>{{ $DTE['receptor']['correo'] }}</p>
                    @endif
                </td>
            </tr>
        </tbody>
    </table> 
    <table class="table bordered dte-detalle">
        @php
            $dteCuentaTerceros = floatval($DTE['resumen']['totalNoGravado'] ?? 0);
            $ventaCuentaTerceros = floatval($registro->cuenta_a_terceros ?? 0);
            $muestraColCuentaTerceros = $dteCuentaTerceros > 0.0001 || $ventaCuentaTerceros > 0.0001;
            $montoCuentaTercerosResumen = $dteCuentaTerceros > 0.0001 ? $dteCuentaTerceros : $ventaCuentaTerceros;
        @endphp
        <thead>
            <tr class="bg-light">
                <th width="3%" class="border-bottom">N°</th>
                <th width="5%" class="border-bottom">Cantidad</th>
                <th width="5%" class="border-bottom">Código</th>
                <th class="border-bottom">Descripción</th>
                <th width="10%" class="border-bottom text-right">Precio Unitario</th>
                <th width="10%" class="border-bottom text-right">Descuento por ítem</th>
                @if ($muestraColCuentaTerceros)
                    <th width="10%" class="border-bottom text-right">Otros Montos no Afectos</th>
                @endif
                <th width="10%" class="border-bottom text-right">Ventas No Sujetas</th>
                <th width="10%" class="border-bottom text-right">Ventas Exentas</th>
                <th width="10%" class="border-bottom text-right">Ventas Gravadas</th>
            </tr>
        </thead>
        <tbody>
            @php
                $empresaDtePdf = $registro->empresa()->first();
                $dteMostrarDescripcionProducto = $empresaDtePdf
                    ? (bool) $empresaDtePdf->getCustomConfigValue('configuraciones', 'dte_mostrar_descripcion_producto', true)
                    : true;
            @endphp
            @foreach($DTE['cuerpoDocumento'] as $detalle)
            <tr>
                <td class="border-bottom">   {{ $detalle['numItem']  }}</td>
                <td class="border-bottom">   {{ number_format($detalle['cantidad'] , 2) }}</td>
                <td class="border-bottom">   {{ $detalle['codigo']  }}</td>
                <td class="border-bottom">
                    {{ $detalle['descripcion']  }}
                    @if ($dteMostrarDescripcionProducto && ($detReg = $registro->detalles->where('descripcion', $detalle['descripcion'])->first()) && $detReg->producto)
                        <br>
                        <span class="text-muted">
                            {!! nl2br(e($detReg->producto->descripcion)) !!}
                        </span>
                    @endif
                </td>
                <td class="border-bottom text-right">   ${{number_format($detalle['precioUni'] , 2) }}</td>
                <td class="border-bottom text-right">   ${{number_format($detalle['montoDescu'] , 2) }}</td>
                @if ($muestraColCuentaTerceros)
                    <td class="border-bottom text-right">${{ number_format((float) ($detalle['noGravado'] ?? 0), 2) }}</td>
                @endif
                <td class="border-bottom text-right">${{ number_format((float) ($detalle['ventaNoSuj'] ?? 0), 2) }}</td>
                <td class="border-bottom text-right">${{ number_format((float) ($detalle['ventaExenta'] ?? 0), 2) }}</td>
                <td class="border-bottom text-right">${{ number_format((float) ($detalle['ventaGravada'] ?? 0), 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4"></td>
                <td class="bg-light" colspan="2"><b>Suma de ventas:</b> </td>
                @if ($muestraColCuentaTerceros)
                    <td class="bg-light text-right">${{ number_format($montoCuentaTercerosResumen, 2) }}</td>
                @endif
                <td class="bg-light text-right">${{ number_format($DTE['resumen']['totalNoSuj'], 2) }}</td>
                <td class="bg-light text-right">${{ number_format($DTE['resumen']['totalExenta'], 2) }}</td>
                <td class="bg-light text-right">${{ number_format($DTE['resumen']['totalGravada'], 2) }}</td>
            </tr>
            <tr>
                <td colspan="4"></td>
                <td colspan="{{ $muestraColCuentaTerceros ? 5 : 4 }}">Suma total de operaciones: </td>
                <td class="text-right">${{ number_format($DTE['resumen']['subTotalVentas'], 2) }}</td>
            </tr>
            <tr>
                <td colspan="4"></td>
                <td colspan="{{ $muestraColCuentaTerceros ? 5 : 4 }}">Monto global Desc., Rebajas y otros a ventas no sujetas: </td>
                <td class="text-right">${{ number_format($DTE['resumen']['descuNoSuj'], 2) }}</td>
            </tr>
            <tr>
                <td colspan="4"></td>
                <td colspan="{{ $muestraColCuentaTerceros ? 5 : 4 }}">Monto global Desc., Rebajas y otros a ventas exentas: </td>
                <td class="text-right">${{ number_format($DTE['resumen']['descuExenta'], 2) }}</td>
            </tr>
            <tr>
                <td colspan="4"></td>
                <td colspan="{{ $muestraColCuentaTerceros ? 5 : 4 }}">Monto global Desc., Rebajas y otros a ventas gravadas: </td>
                <td class="text-right">${{ number_format($DTE['resumen']['descuGravada'], 2) }}</td>
            </tr>

            @if (isset($DTE['resumen']['tributos']))
                @foreach ($DTE['resumen']['tributos'] as $tributo)
                <tr>
                    <td colspan="4"></td>
                    <td colspan="{{ $muestraColCuentaTerceros ? 5 : 4 }}">{{ $tributo['descripcion'] }}: </td>
                    <td class="text-right">${{ number_format($tributo['valor'], 2) }}</td>
                </tr>
                @endforeach
            @endif
            <tr>
                <td colspan="4"></td>
                <td colspan="{{ $muestraColCuentaTerceros ? 5 : 4 }}">Sub-Total: </td>
                <td class="text-right">${{ number_format($DTE['resumen']['subTotal'], 2) }}</td>
            </tr>
            <tr>
                <td colspan="4"></td>
                <td colspan="{{ $muestraColCuentaTerceros ? 5 : 4 }}">IVA Percibido: (+) </td>
                <td class="text-right">${{ number_format(0, 2) }}</td>
            </tr>
            <tr>
                <td colspan="4"></td>
                <td colspan="{{ $muestraColCuentaTerceros ? 5 : 4 }}">IVA Retenido: (-) </td>
                <td class="text-right">${{ number_format($DTE['resumen']['ivaRete1'], 2) }}</td>
            </tr>
            <tr>
                <td colspan="4"></td>
                <td colspan="{{ $muestraColCuentaTerceros ? 5 : 4 }}">Retención de Renta: </td>
                <td class="text-right">${{ number_format($DTE['resumen']['reteRenta'], 2) }}</td>
            </tr>
            @if(isset($registro->propina) && floatval($registro->propina) > 0)
                <tr>
                    <td colspan="4"></td>
                    <td colspan="{{ $muestraColCuentaTerceros ? 5 : 4 }}">Propina: </td>
                    <td class="text-right">${{ number_format(floatval($registro->propina), 2) }}</td>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td colspan="{{ $muestraColCuentaTerceros ? 5 : 4 }}">Total: </td>
                    <td class="text-right">${{ number_format(floatval($DTE['resumen']['montoTotalOperacion'] ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td colspan="{{ $muestraColCuentaTerceros ? 5 : 4 }}"><b>Total + Propina: </b></td>
                    <td class="text-right"><b>${{ number_format(floatval($DTE['resumen']['montoTotalOperacion'] ?? 0) + floatval($registro->propina), 2) }}</b></td>
                </tr>
            @else
                <tr>
                    <td colspan="4"></td>
                    <td colspan="{{ $muestraColCuentaTerceros ? 5 : 4 }}">Monto Total de la Operación: </td>
                    <td class="text-right">${{ number_format(floatval($DTE['resumen']['montoTotalOperacion'] ?? 0), 2) }}</td>
                </tr>
            @endif
            @if ($muestraColCuentaTerceros)
                <tr>
                    <td colspan="4"></td>
                    <td colspan="{{ $muestraColCuentaTerceros ? 5 : 4 }}">Total Otros montos no afectos: </td>
                    <td class="text-right">${{ number_format($montoCuentaTercerosResumen, 2) }}</td>
                </tr>
            @endif
            <tr>
                <td colspan="4"></td>
                <td colspan="{{ $muestraColCuentaTerceros ? 5 : 4 }}" class="bg-light"><b>Total a pagar:</b></td>
                <td class="bg-light text-right"><b>${{ number_format(floatval($DTE['resumen']['totalPagar'] ?? 0) + (isset($registro->propina) && floatval($registro->propina) > 0 ? floatval($registro->propina) : 0), 2) }}</b></td>
            </tr>
        </tfoot>
    </table>
    <table class="table bordered">
        <tbody>
            <tr>
                <td width="50%"><b>Valor en Letras:</b> {{ $DTE['resumen']['totalLetras'] }}</td>
                <td width="50%">
                    <b>Condición de la operación: </b>
                    @if ($DTE['resumen']['condicionOperacion'] == 2)
                        Crédito a {{ \Carbon\Carbon::parse($registro->fecha)->diffInDays(\Carbon\Carbon::parse($registro->fecha_pago), false) }} días
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
