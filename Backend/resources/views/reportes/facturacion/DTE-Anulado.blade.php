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
                            <img height="100" src="{{ asset('img/'.$registro->empresa()->pluck('logo')->first()) }}" alt="Logo">
                        @endif
                    </td>
                    <td style="width: 50%; text-align: center;">
                        <h2>DOCUMENTO DE INVALIDACIÓN</h2>
                        {{-- <h2>FACTURA</h2> --}}
                    </td>
                    <td style="width: 25%; text-align: right;">
                        {!! '<img id="qrcode" width="150" height="150" src="data:image/png;base64,' . DNS2D::getBarcodePNG($registro->qr, 'QRCODE', 10, 10, array(0,0,0), true) . '" alt="barcode"   />' !!}
                    </td>
                </tr>
            </tbody>
        </table>
        <br>
        <table class="table bordered">
            <tbody>
                <tr>
                    <td style="width: 50%;">
                        <p><b>Código de Generación:</b> {{ $DTE['identificacion']['codigoGeneracion'] }}</p>
                    </td>
                    <td style="width: 50%;">
                        <p><b>Fecha y Hora de Generación:</b> {{ \Carbon\Carbon::parse($DTE['identificacion']['fecAnula'] . ' ' . $DTE['identificacion']['horAnula'])->format('d/m/Y H:i:s') }}</p>
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
                        @if (isset($DTE['emisor']['nombreComercial']))
                            <p><b>Nombre comercial: </b>{{ $DTE['emisor']['nombreComercial'] }}</p>
                        @endif
                        <p><b>Nombre o razón social: </b>{{ $DTE['emisor']['nombre'] }}</p>
                        <p><b>NIT:</b> {{ $DTE['emisor']['nit'] }}</p>
                        <p><b>Teléfono: </b>{{ $DTE['emisor']['telefono'] }}</p>
                        <p><b>Correo: </b>{{ $DTE['emisor']['correo'] }}</p>
                        <br>
                    </td>
                    <td style="width: 50%; vertical-align: top;">
                        <p><b>Nombre: </b>{{ $DTE['documento']['nombre'] }}</p>
                        <p><b>Tipo de Documento:</b> 
                            @if ($DTE['documento']['tipoDocumento'] == '36')
                                 NIT
                            @endif
                            @if ($DTE['documento']['tipoDocumento'] == '13')
                                 DUI
                            @endif
                        </p>
                        <p><b>Documento: </b>{{ $DTE['documento']['numDocumento'] }}</p>
                        <p><b>Correo: </b>{{ $DTE['documento']['correo'] }}</p>
                        <p><b>Teléfono: </b>{{ $DTE['documento']['telefono'] }}</p>
                    </td>
                </tr>
            </tbody>
        </table> 

        <br>

        <table class="table bordered">
            <tbody>
                <tr>
                    <td><b>Tipo DTE: </b></td>
                    <td>{{ $DTE['documento']['tipoDte'] }}</td></tr>
                <tr>
                    <td><b>Código de generación:</b></td>
                    <td>{{ $DTE['documento']['codigoGeneracion'] }}</td></tr>
                <tr>
                    <td><b>Sello:</b> </td>
                    <td>{{ $DTE['documento']['selloRecibido'] }}</td></tr>
                <tr>
                    <td><b>Número de control:</b> </td>
                    <td>{{ $DTE['documento']['numeroControl'] }}</td></tr>
                <tr>
                    <td><b>Fecha:</b> </td>
                    <td>{{ $DTE['documento']['fecEmi'] }}</td></tr>
                <tr>
                    <td> <b>Motivo de anulación:</b> </td>
                    <td> {{ $DTE['motivo']['motivoAnulacion'] }} </td>
                </tr>
                <tr>
                    <td> <b>Responsable:</b> </td>
                    <td> {{ $DTE['motivo']['nombreResponsable'] }} </td>
                </tr>
                <tr>
                    <td> <b>Número de Documento:</b> </td>
                    <td> {{ $DTE['motivo']['numDocResponsable'] }} </td>
                </tr>
            </tbody>
        </table>

</body>
</html>
