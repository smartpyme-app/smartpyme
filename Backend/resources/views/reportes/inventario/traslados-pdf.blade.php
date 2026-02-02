<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Traslados</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/5.0.2/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <style>
        @page {
            margin: 40px 60px;
        }
        html {
            margin: 40px 60px;
        }
        body {
            font-family: 'Inter', sans-serif;
            font-family: 'Nunito', sans-serif;
            width: 100%;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        th, td {
            font-size: 11px;
            text-align: left;
            padding: 6px;
            border: solid 0.5px;
            border-color: lightgray;
        }
        #empresa {
            margin-bottom: 0px;
            padding-top: 0px;
            text-align: center;
            margin-top: 0px;
            margin-bottom: 5px;
        }
        p {
            font-size: 12px;
            margin: 5px;
        }
        #table {
            margin-top: 0px;
            padding: 0px;
            width: 100%;
        }
        #headtable {
            padding: 8px;
            background-color: #1775e5;
            margin: 0px;
            color: white;
            font-size: 10px;
        }
        #img {
            height: 25px;
            margin-bottom: 15px;
        }
        .text-right {
            text-align: right !important;
        }
        .text-center {
            text-align: center !important;
        }
        .traslado-section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        .traslado-header {
            background-color: #f8f9fa;
            padding: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #1775e5;
        }
        .firma-section {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }
        .firma-box {
            display: inline-block;
            width: 45%;
            margin: 10px 2%;
            vertical-align: top;
        }
        .firma-line {
            border-top: 1px solid #333;
            margin-top: 50px;
            padding-top: 5px;
            text-align: center;
            font-size: 11px;
        }
        .info-section {
            margin-bottom: 15px;
        }
        .page-break {
            page-break-after: always;
        }
        .concepto-header {
            background-color: #1775e5;
            color: white;
            padding: 12px;
            margin: 20px 0 15px 0;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
            page-break-after: avoid;
        }
        .concepto-group {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        .page-break-after {
            page-break-after: always;
        }
        .footer-info {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            page-break-before: avoid;
        }
    </style>
</head>
<body>
    @php
        $totalTraslados = 0;
    @endphp

    @foreach($trasladosAgrupados as $concepto => $trasladosGrupo)
    @php
        // Agrupar traslados por bodega de origen y destino dentro del mismo concepto
        $trasladosPorBodegas = $trasladosGrupo->groupBy(function($traslado) {
            return ($traslado->id_bodega_de ?? '') . '-' . ($traslado->id_bodega ?? '');
        });
        $totalTraslados += count($trasladosGrupo);
    @endphp
    
    <div class="concepto-group {{ !$loop->last ? 'page-break-after' : '' }}">
        <div class="header">
            <div class="col-lg-12 text-center">
                <h3 class="text-center" id="empresa">{{ $empresa->nombre ?? 'SmartPyme' }}</h3>
            </div>
            <h5 class="text-center" id="empresa">Reporte de Traslados de Inventario</h5>
            <p class="text-center" style="font-size: 11px; color: #666;">Generado el {{ \Carbon\Carbon::now()->format('d/m/Y h:i:s a') }}</p>
            <p style="font-size: 10px; color: #666; text-align: center; margin: 0;">
                Este documento avala los movimientos de inventario entre bodegas.
                <br>
                Total de traslados: {{ $totalTraslados }}
                <br>
            </p>
            <br>
        </div>
        
        <div class="concepto-header">
            <h5 style="margin: 0; font-size: 14px;">CONCEPTO: {{ $concepto }}</h5>
            <p style="margin: 5px 0 0 0; font-size: 11px; font-weight: normal;">Total de productos en este concepto: {{ count($trasladosGrupo) }}</p>
        </div>

        @foreach($trasladosPorBodegas as $keyBodegas => $trasladosBodega)
        @php
            $primerTraslado = $trasladosBodega->first();
            $totalCosto = $trasladosBodega->sum(function($t) {
                return ($t->costo ?? 0) * $t->cantidad;
            });
        @endphp

        <div class="traslado-section">
            <div class="col-lg-12 info-section">
                <p><b>Fecha:</b> {{ \Carbon\Carbon::parse($primerTraslado->created_at)->format('d/m/Y h:i:s a') }}</p>
                <p><b>Estado:</b> 
                    <span style="background-color: {{ $primerTraslado->estado == 'Confirmado' ? '#DCFCE7' : '#FEE2E2' }}; 
                                color: {{ $primerTraslado->estado == 'Confirmado' ? '#14532D' : '#991B1B' }}; 
                                padding: 3px 8px; border-radius: 4px; font-size: 10px;">
                        {{ $primerTraslado->estado }}
                    </span>
                </p>
                <p><b>Realizado por:</b> {{ $primerTraslado->usuario->name ?? 'N/A' }}</p>
                <p><b>Bodega de Origen:</b> {{ $primerTraslado->origen->nombre ?? 'N/A' }}</p>
                <p><b>Bodega de Destino:</b> {{ $primerTraslado->destino->nombre ?? 'N/A' }}</p>
            </div>

            <div class="col-lg-12">
                <table cellspacing="0" cellpadding="0" id="table">
                    <thead id="headtable">
                        <tr>
                            <th>PRODUCTO</th>
                            <th class="text-center">CANTIDAD</th>
                            <th class="text-right">COSTO UNITARIO</th>
                            <th class="text-right">COSTO TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($trasladosBodega as $traslado)
                        <tr>
                            <td>
                                {{ $traslado->producto->nombre ?? 'N/A' }}
                                @if($traslado->producto && isset($traslado->producto->nombre_variante) && $traslado->producto->nombre_variante)
                                    - {{ $traslado->producto->nombre_variante }}
                                @endif
                                @if($traslado->producto && $traslado->producto->codigo)
                                    <br><span style="font-size: 9px; color: #666;">SKU: {{ $traslado->producto->codigo }}</span>
                                @endif
                            </td>
                            <td class="text-center">{{ number_format($traslado->cantidad, 0) }}</td>
                            <td class="text-right">${{ number_format($traslado->costo ?? 0, 2) }}</td>
                            <td class="text-right">${{ number_format(($traslado->costo ?? 0) * $traslado->cantidad, 2) }}</td>
                        </tr>
                        @endforeach
                        <tr style="background-color: #f8f9fa; font-weight: bold;">
                            <td colspan="3" class="text-right"><b>TOTAL:</b></td>
                            <td class="text-right">${{ number_format($totalCosto, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="col-lg-12 firma-section">
                <div class="firma-box">
                    <p class="text-center" style="font-size: 11px; margin-bottom: 5px;"><b>ENTREGADO POR</b></p>
                    <div class="firma-line">
                        <p style="font-size: 10px;">{{ $primerTraslado->origen->nombre ?? 'Bodega de Origen' }}</p>
                        <p style="font-size: 9px;">Firma y sello</p>
                    </div>
                </div>
                <div class="firma-box">
                    <p class="text-center" style="font-size: 11px; margin-bottom: 5px;"><b>RECIBIDO POR</b></p>
                    <div class="firma-line">
                        <p style="font-size: 10px;">{{ $primerTraslado->destino->nombre ?? 'Bodega de Destino' }}</p>
                        <p style="font-size: 9px;">Firma y sello</p>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endforeach
</body>
</html>
