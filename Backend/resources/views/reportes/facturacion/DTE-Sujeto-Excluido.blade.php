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
            font-family: serif; margin: 50px;
            font-size: 10px;
        }
        h1,h2,h3,h4,h5,h6{color: #003 !important; }

        .table{width: 100%; border-collapse: collapse; }
        .table th, .table td{
            border-collapse: collapse;
            padding: 5px;
            text-align: left;
        }

        .table.bordered th, .table.bordered td{
            border: 1px solid #aaa;
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

        <table class="table">
            <tbody>
                <tr>
                    <td  style="width: 25%;">
                        {{-- Logo --}}
                        @if ($registro->empresa()->pluck('logo')->first())
                            <img width="150" src="{{ asset('img/'.$registro->empresa()->pluck('logo')->first()) }}" alt="Logo">
                        @endif
                    </td>
                    <td style="width: 50%; text-align: center;">
                        <h2>DOCUMENTO TRIBUTARIO ELECTRÓNICO</h2>
                        <h2>FACTURA SUJETO EXCLUIDO</h2>
                    </td>
                    <td style="width: 25%; text-align: right;">
                        {!! '<img id="qrcode" width="150" height="150" src="data:image/png;base64,' . DNS2D::getBarcodePNG($registro->qr, 'QRCODE', 10, 10, array(0,0,0), true) . '" alt="barcode"   />' !!}
                    </td>
                </tr>
            </tbody>
        </table>
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
        <br>
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
        @php
            $tipoDocumento = [
                    '36',
                    '13'
            ];
        @endphp
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
                        <p><b>Dirección:</b> {{ $DTE['emisor']['direccion']['complemento'] }}
                            {{ $registro->empresa()->pluck('municipio')->first(); }}
                            {{ $registro->empresa()->pluck('departamento')->first(); }}
                        </p>
                        
                        <p><b>Teléfono: </b>{{ $DTE['emisor']['telefono'] }}</p>
                        <p><b>Correo: </b>{{ $DTE['emisor']['correo'] }}</p>
                        <br>
                    </td>
                    <td style="width: 50%; vertical-align: top;">
                        @if ($DTE['sujetoExcluido'])
                            <p><b>Nombre o razón social: </b>{{ $DTE['sujetoExcluido']['nombre'] }}</p>
                            <p><b>Tipo de Documento:</b> {{ $DTE['sujetoExcluido']['tipoDocumento'] == '36' ? 'NIT' : 'DUI' }}</p>
                            <p><b>Núm de Documento:</b> {{ $DTE['sujetoExcluido']['numDocumento'] }}</p>
                            <p><b>Act. económica:</b> {{ $DTE['sujetoExcluido']['descActividad'] }}</p>
                            <p><b>Dirección:</b> {{ $DTE['sujetoExcluido']['direccion']['complemento'] }}
                                {{ $registro->proveedor()->pluck('municipio')->first(); }}
                                {{ $registro->proveedor()->pluck('departamento')->first(); }}
                            </p>
                            <p><b>Teléfono: </b>{{ $DTE['sujetoExcluido']['telefono'] }}</p>
                            <p><b>Correo: </b>{{ $DTE['sujetoExcluido']['correo'] }}</p>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table> 

        <br>

        <table class="table bordered">
            <thead>
                <tr class="bg-light">
                    <th width="3%" class="border-bottom">N°</th>
                    <th width="5%" class="border-bottom">Cantidad</th>
                    <th width="5%" class="border-bottom">Código</th>
                    <th class="border-bottom">Descripción</th>
                    <th width="10%" class="border-bottom text-right">Precio Unitario</th>
                    <th width="10%" class="border-bottom text-right">Descuento por ítem</th>
                    <th width="10%" class="border-bottom text-right">Compras</th>
                </tr>
            </thead>
            <tbody>
                @foreach($DTE['cuerpoDocumento'] as $detalle)
                <tr>
                    <td class="border-bottom">   {{ $detalle['numItem']  }}</td>
                    <td class="border-bottom">   {{ number_format($detalle['cantidad'] , 4) }}</td>
                    <td class="border-bottom">   {{ $detalle['codigo']  }}</td>
                    <td class="border-bottom">   {{ $detalle['descripcion']  }}</td>
                    <td class="border-bottom text-right">   ${{number_format($detalle['precioUni'] , 4) }}</td>
                    <td class="border-bottom text-right">   ${{number_format($detalle['montoDescu'] , 2) }}</td>
                    <td class="border-bottom text-right">   ${{ number_format($detalle['compra'], 2) }}</th>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4"></td>
                    <td class="bg-light" colspan="2"><b>Suma de compras:</b> </td>
                    <td class="bg-light text-right">${{ number_format($DTE['resumen']['totalCompra'], 2) }}</td>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td colspan="2">Monto global Desc., Rebajas y otros a compras: </td>
                    <td class="text-right">${{ number_format($DTE['resumen']['totalDescu'], 2) }}</td>
                </tr>

                @if (isset($DTE['resumen']['tributos']))
                    @foreach ($DTE['resumen']['tributos'] as $tributo)
                    <tr>
                        <td colspan="4"></td>
                        <td colspan="2">{{ $tributo['descripcion'] }}: </td>
                        <td class="text-right">${{ number_format($tributo['valor'], 2) }}</td>
                    </tr>
                    @endforeach
                @endif
                <tr>
                    <td colspan="4"></td>
                    <td colspan="2">Sub-Total: </td>
                    <td class="text-right">${{ number_format($DTE['resumen']['subTotal'], 2) }}</td>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td colspan="2">IVA Percibido: (+) </td>
                    <td class="text-right">${{ number_format(0, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td colspan="2">IVA Retenido: (-) </td>
                    <td class="text-right">${{ number_format($DTE['resumen']['ivaRete1'], 2) }}</td>
                </tr>
                <tr>
                    <td colspan="4"></td>
                    <td colspan="2">Retención de Renta: </td>
                    <td class="text-right">${{ number_format($DTE['resumen']['reteRenta'], 2) }}</td>
                </tr>
                @if(isset($registro->propina) && floatval($registro->propina) > 0)
                    <tr>
                        <td colspan="4"></td>
                        <td colspan="2">Propina: </td>
                        <td class="text-right">${{ number_format(floatval($registro->propina), 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="4"></td>
                        <td class="bg-light" colspan="2"><b>Total:</b></td>
                        <td class="bg-light text-right"><b>${{ number_format(floatval($DTE['resumen']['totalPagar'] ?? 0), 2) }}</b></td>
                    </tr>
                    <tr>
                        <td colspan="4"></td>
                        <td class="bg-light" colspan="2"><b>Total + Propina:</b></td>
                        <td class="bg-light text-right"><b>${{ number_format(floatval($DTE['resumen']['totalPagar'] ?? 0) + floatval($registro->propina), 2) }}</b></td>
                    </tr>
                @endif
                <tr>
                    <td colspan="4"></td>
                    <td class="bg-light" colspan="2"><b>Total a pagar:</b></td>
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
                            Crédito
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
