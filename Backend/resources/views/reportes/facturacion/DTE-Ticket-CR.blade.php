<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  <script language="javascript">setTimeout("self.close();",2000)</script>
  <title>{{ $clave ?: 'Tiquete FE CR' }}</title>
  <style media="all">
    @if ($venta->pdf)
        body{ width: 80mm; margin: 0;}
    @endif
    h1, h2, h3{
        margin: 3pt;
    }
    .header, .footer{
        text-align: center;
    }
    html, body {
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI",
    "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Fira Sans",
    "Droid Sans", "Helvetica Neue", sans-serif;
        margin: 0pt;
        padding: 0pt;
        font-size: 9pt;
    }

    p{ margin: 0px; }
    table td{height: 12pt;}
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-left { text-align: left; }
    .clave-bloque { font-size: 8pt; line-height: 1.25; word-break: break-all; }
  </style>

  <style media="print"> .no-print{display: none; } </style>

</head>
<body onload="javascript:print();">

@php
    $sum = $documentoFe['summary'] ?? [];
    if (! is_array($sum)) { $sum = []; }
    $lines = $documentoFe['line_items'] ?? [];
    if (! is_array($lines)) { $lines = []; }
    $currency = $documentoFe['currency'] ?? [];
    $monedaCod = strtoupper((string) ($currency['currency_code'] ?? 'CRC'));
    $simbolo = $monedaCod === 'USD' ? 'USD' : '₡';
    $est = (string) ($documentoFe['establishment'] ?? '');
    $punto = (string) ($documentoFe['emission_point'] ?? '');
    $seq = (string) ($documentoFe['sequential'] ?? '');
    $consecutivo = implode('-', array_filter([$est, $punto, $seq], static fn ($x) => $x !== '' && $x !== null));
    $claveFmt = $feCrPdf['clave_formateada'] ?? (string) $clave;
    $claveSoloDigitos = preg_replace('/\D/', '', (string) $clave);
    $qrPayload = strlen($claveSoloDigitos) >= 40 ? $claveSoloDigitos : 'https://atv.hacienda.go.cr/ATV/frmConsultaFactura.aspx';
    $sucursal = $venta->sucursal ?? ($venta->relationLoaded('sucursal') ? $venta->sucursal : $venta->sucursal()->first());
@endphp

    <div class="header">
        @if (!$venta->pdf)
            <p class="no-print">
                <button onClick="window.print();" autofocus>Imprimir</button>
                <button onClick="window.close();" autofocus>Cerrar</button>
                <br><br>
            </p>
            <br>
        @endif

        @if ($sucursal)
            <h3>{{ $sucursal->nombre }}</h3>
        @else
            <h3>{{ $empresa->nombre }}</h3>
        @endif
        <p>{{ $empresa->sector }}</p>
        <p>{{ $empresa->nombre_propietario }}</p>

        @if ($sucursal && $sucursal->direccion)
            <p>{{ $sucursal->direccion }}</p>
        @else
            <p>{{ $empresa->direccion }}</p>
        @endif
        @if($empresa->ncr)
            <p><b>Identificación tributaria:</b> {{ $empresa->ncr }}</p>
        @endif
        @if($empresa->nit)
            <p><b>Cédula/NITE:</b> {{ $empresa->nit }}</p>
        @endif
        @if($empresa->giro)
            <p><b>Actividad económica:</b> {{ $empresa->giro }}</p>
        @endif

        @if ($sucursal && $sucursal->telefono)
            <p><b>TELÉFONO:</b> {{ $sucursal->telefono }}</p>
        @elseif($empresa->telefono)
            <p><b>TELÉFONO:</b> {{ $empresa->telefono }}</p>
        @endif

        <p><b>{{ $titulo ?? 'Comprobante electrónico' }}</b></p>
        <p>
            <b>FECHA Y HORA:</b> <br>
            {{ \Carbon\Carbon::parse($venta->created_at)->timezone('America/Costa_Rica')->format('d/m/Y') }}
            | {{ \Carbon\Carbon::parse($venta->created_at)->timezone('America/Costa_Rica')->format('h:i:s a') }}
        </p>
        <p><b>CONSECUTIVO:</b> {{ $consecutivo !== '' ? $consecutivo : $venta->correlativo }}</p>
        <p><b>CAJERO:</b> {{ $venta->nombre_usuario }}</p>

        @if ($venta->cliente)
            <p><b>Cliente:</b></p>
            <p>Nombre: {{ $venta->nombre_cliente }}</p>
            @if ($venta->cliente->telefono)
                <p>Teléfono: {{ $venta->cliente->telefono }}</p>
            @endif
            @if ($venta->cliente->direccion)
                <p>Dirección: {{ $venta->cliente->direccion }}</p>
            @endif
        @endif

        <p><b>CLAVE NUMÉRICA:</b></p>
        <p class="clave-bloque">{{ $claveFmt }}</p>
        @if ($consecutivo !== '')
            <p><b>CONSECUTIVO FE:</b> {{ $consecutivo }}</p>
        @endif
    </div>

    <br>
    <p class="text-center">
        {!! '<img id="qrcode" width="150" height="150" src="data:image/png;base64,' . DNS2D::getBarcodePNG($qrPayload, 'QRCODE', 10, 10, array(0,0,0), true) . '" alt="Código QR" />' !!}
    </p>

    <hr>

    <table style="width: 80mm; max-width: 80mm; margin: auto;">
        <thead>
            <tr>
                <th style="max-width: 50%" class="text-left">DETALLE</th>
                <th width="10%" class="text-center">CANT</th>
                <th width="10%" class="text-center">P.U.</th>
                <th width="10%" class="text-right">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @if ($venta->descripcion_personalizada)
                <tr>
                    <td>{{ $venta->descripcion_impresion }}</td>
                    <td class="text-center">1</td>
                    <td class="text-center">{{ $simbolo }}{{ number_format((float) ($sum['total'] ?? $venta->total), 2) }}</td>
                    <td class="text-right">{{ $simbolo }}{{ number_format((float) ($sum['total'] ?? $venta->total), 2) }}</td>
                </tr>
            @else
                @foreach($lines as $line)
                    @if(is_array($line))
                    <tr>
                        <td>{{ $line['description'] ?? '' }}</td>
                        <td class="text-center">{{ number_format((float) ($line['quantity'] ?? 0), 2) }}</td>
                        <td class="text-center">{{ $simbolo }}{{ number_format((float) ($line['unit_price'] ?? 0), 2) }}</td>
                        <td class="text-right">{{ $simbolo }}{{ number_format((float) ($line['total'] ?? 0), 2) }}</td>
                    </tr>
                    @endif
                @endforeach
            @endif
        </tbody>
        <tfoot>
            <tr>
                <td class="text-right" colspan="3">Total gravado:</td>
                <td class="text-right">{{ $simbolo }}{{ number_format((float) ($sum['total_taxed'] ?? $venta->gravada), 2) }}</td>
            </tr>
            <tr>
                <td class="text-right" colspan="3">Total exento:</td>
                <td class="text-right">{{ $simbolo }}{{ number_format((float) ($sum['total_exempt'] ?? $venta->exenta), 2) }}</td>
            </tr>
            <tr>
                <td class="text-right" colspan="3">Total no sujeto:</td>
                <td class="text-right">{{ $simbolo }}{{ number_format((float) ($sum['total_non_taxable'] ?? $venta->no_sujeta), 2) }}</td>
            </tr>
            <tr>
                <td class="text-right" colspan="3">IVA:</td>
                <td class="text-right">{{ $simbolo }}{{ number_format((float) ($sum['total_tax'] ?? $venta->iva), 2) }}</td>
            </tr>
            @if(isset($venta->propina) && floatval($venta->propina) > 0)
                <tr>
                    <td class="text-right" colspan="3">Propina:</td>
                    <td class="text-right">{{ $simbolo }}{{ number_format(floatval($venta->propina), 2) }}</td>
                </tr>
            @endif
            <tr>
                <td class="text-right" colspan="3"><b>TOTAL</b>:</td>
                <td class="text-right"><b>{{ $simbolo }}{{ number_format((float) ($sum['total'] ?? $venta->total), 2) }}</b></td>
            </tr>
        </tfoot>
    </table>

    <br>
    <hr style="margin: 5px;">

    <table style="margin: auto;">
        <tr>
            <td>Método de pago:</td><td class="text-right">{{ $venta->forma_pago }}</td>
        </tr>
        <tr>
            <td>Recibido:</td><td class="text-right">{{ $simbolo }}{{ number_format($venta->monto_pago, 2) }}</td>
        </tr>
        <tr>
            <td>Cambio:</td><td class="text-right">{{ $simbolo }}{{ number_format($venta->cambio, 2) }}</td>
        </tr>
    </table>

    @if(!empty($feCrPdf['total_en_letras'] ?? ''))
        <p class="text-center" style="font-size: 8pt; margin-top: 6px;"><b>Son:</b> {{ $feCrPdf['total_en_letras'] }}</p>
    @endif

    <p class="text-center" style="font-size: 7pt; margin-top: 6px;">
        Representación gráfica del comprobante electrónico CR.<br>
        Consulta: atv.hacienda.go.cr
    </p>

    @include('reportes.facturacion.partials.documento-nota')

    <br>
    <p style="color: #fff;">.</p>

</body>
</html>
