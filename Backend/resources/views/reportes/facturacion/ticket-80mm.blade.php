<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $documento->nombre ?? 'Ticket' }}</title>
    <style>
        @page { margin: 4mm; }
        html, body {
            margin: 0;
            padding: 0;
            width: 80mm;
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.5pt;
            color: #111;
        }
        p { margin: 0; }
        .cen { text-align: center; }
        .b { font-weight: bold; }
        .muted { color: #222; }
        .sec { margin-top: 6px; }
        .sec-title { font-weight: bold; text-transform: uppercase; font-size: 8pt; margin-bottom: 2px; }
        hr { border: 0; border-top: 1px dashed #444; margin: 6px 0; }
        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th, table.lines td { font-size: 8pt; vertical-align: top; padding: 1px 0; }
        .num, .val { text-align: right; white-space: nowrap; }
        .left { text-align: left; }
        .logo { max-width: 28mm; max-height: 16mm; }
        .foot { text-align: center; font-size: 7pt; }
    </style>
</head>
<body>
@php
    $codigoPais = \App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver::resolveCodigoPaisFe($empresa);
    $esHonduras = $codigoPais === \App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver::CODIGO_HONDURAS;
    $esSalvador = $codigoPais === \App\Services\FacturacionElectronica\FacturacionElectronicaCountryResolver::CODIGO_EL_SALVADOR;
    $moneda = trim((string) ($empresa->moneda ?? ''));
    if ($moneda === '') {
        $moneda = $esHonduras ? 'HNL' : 'USD';
    }
    $etiquetaFiscal = $esHonduras ? 'RTN' : ($esSalvador ? 'NIT' : 'Identificación fiscal');
    $etiquetaImpuesto = $esHonduras ? 'ISV' : 'IVA';

    $sucursal = $venta->relationLoaded('sucursal') ? $venta->getRelation('sucursal') : null;
    $direccion = ($sucursal && trim((string) ($sucursal->direccion ?? '')) !== '')
        ? $sucursal->direccion
        : trim((string) ($empresa->direccion ?? ''));
    $telefono = ($sucursal && trim((string) ($sucursal->telefono ?? '')) !== '')
        ? $sucursal->telefono
        : ($empresa->telefono ?? null);
    $correo = ($sucursal && trim((string) ($sucursal->correo ?? '')) !== '')
        ? $sucursal->correo
        : ($empresa->correo ?? null);
    $nombreSucursal = ($sucursal && trim((string) ($sucursal->nombre ?? '')) !== '')
        ? $sucursal->nombre
        : null;

    $numero = $esHonduras
        ? \App\Support\Honduras\DocumentoImpresionHn::correlativo($documento, $venta->correlativo)
        : (string) ($venta->correlativo ?? '');

    $fecha = $venta->fecha ? \Carbon\Carbon::parse($venta->fecha) : \Carbon\Carbon::now();
    if ($venta->created_at) {
        $hora = \Carbon\Carbon::parse($venta->created_at);
        $fecha->setTime($hora->hour, $hora->minute);
    }
    $formaPago = trim((string) ($venta->forma_pago ?: $venta->condicion ?: ''));

    $nombreCliente = trim((string) ($venta->nombre_cliente ?? ''));
    if ($nombreCliente === '' && $cliente) {
        $nombreCliente = trim((string) ($cliente->nombre_empresa ?: $cliente->nombre ?? ''));
    }
    if ($nombreCliente === '') {
        $nombreCliente = 'Consumidor final';
    }
    $docCliente = '';
    if ($cliente) {
        $docCliente = trim((string) ($cliente->nit ?: $cliente->dui ?? ''));
    }

    $cai = '';
    $rango = '';
    $fechaLimite = '';
    $totalesHn = null;
    if ($esHonduras) {
        $cai = trim((string) data_get($empresa->custom_empresa, 'configuraciones.factura_cai'));
        if ($cai === '') {
            $cai = trim((string) ($documento->resolucion ?? ''));
        }
        $rango = trim((string) data_get($empresa->custom_empresa, 'configuraciones.factura_rango_autorizado'));
        if ($rango === '') {
            $rango = trim((string) ($documento->rangos ?? ''));
        }
        if ($rango === '') {
            $rango = trim((string) ($documento->numero_autorizacion ?? ''));
        }
        $fechaLimiteRaw = data_get($empresa->custom_empresa, 'configuraciones.factura_fecha_limite') ?: $documento->fecha;
        if ($fechaLimiteRaw) {
            try {
                $fechaLimite = \Carbon\Carbon::parse($fechaLimiteRaw)->format('d/m/Y');
            } catch (\Throwable $e) {
                $fechaLimite = (string) $fechaLimiteRaw;
            }
        }
        $totalesHn = \App\Support\Honduras\DocumentoImpresionHn::totales($venta->detalles, (float) ($empresa->iva ?? 15));
    }

    $obs = trim((string) ($documento->nota ?? ''));
    $logoSrc = null;
    $logoRaw = $empresa->logo ?? null;
    if ($logoRaw) {
        $logoRel = ltrim(str_replace('\\', '/', (string) $logoRaw), '/');
        if ($logoRel !== '' && !str_contains($logoRel, '..')) {
            $fullLogo = public_path('img'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $logoRel));
            if (is_file($fullLogo) && is_readable($fullLogo)) {
                if (!empty($venta->pdf)) {
                    $ext = strtolower(pathinfo($fullLogo, PATHINFO_EXTENSION));
                    $mime = match ($ext) {
                        'png' => 'image/png',
                        'gif' => 'image/gif',
                        'webp' => 'image/webp',
                        default => 'image/jpeg',
                    };
                    $logoSrc = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($fullLogo));
                } else {
                    $logoSrc = asset('img/'.$logoRel);
                }
            }
        }
    }
@endphp

<div class="cen">
    @if ($logoSrc)
        <img class="logo" src="{{ $logoSrc }}" alt="">
    @endif
    <p class="b">{{ $empresa->nombre }}</p>
    @if ($direccion !== '')
        <p>{{ $direccion }}</p>
    @endif
    @if ($telefono)
        <p>Tel: {{ $telefono }}</p>
    @endif
    @if ($correo)
        <p>{{ $correo }}</p>
    @endif
    @if ($empresa->nit)
        <p><span class="b">{{ $etiquetaFiscal }}:</span> {{ $empresa->nit }}</p>
    @endif
    @if ($esSalvador && $empresa->ncr)
        <p><span class="b">NRC:</span> {{ $empresa->ncr }}</p>
    @endif
</div>

<div class="sec">
    <p class="sec-title">{{ $documento->nombre }}</p>
    @if ($nombreSucursal)
        <p>Sucursal: {{ $nombreSucursal }}</p>
    @endif
    <p><span class="b">Número:</span> {{ $numero }}</p>
    <p><span class="b">Fecha:</span> {{ $fecha->format('d/m/Y H:i') }}</p>
    @if ($formaPago !== '')
        <p><span class="b">Pago:</span> {{ $formaPago }}</p>
    @endif
</div>

<div class="sec">
    <p class="sec-title">Cliente</p>
    <p>{{ $nombreCliente }}</p>
    @if ($docCliente !== '')
        <p>{{ $etiquetaFiscal }}: {{ $docCliente }}</p>
    @endif
</div>

<hr>

<table class="lines">
    <thead>
        <tr>
            <th class="left">Descripción</th>
            <th class="num">Cant.</th>
            <th class="num">Precio</th>
            <th class="num">Total</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($venta->detalles as $detalle)
        @php
            $cant = (float) $detalle->cantidad;
            $totalLinea = (float) ($detalle->total ?? 0);
            $precio = $cant > 0 ? ($totalLinea / $cant) : (float) ($detalle->precio ?? 0);
            $descLinea = trim((string) ($detalle->nombre_producto ?: $detalle->descripcion ?? ''));
        @endphp
        <tr>
            <td class="left">{{ $descLinea }}</td>
            <td class="num">{{ rtrim(rtrim(number_format($cant, 2, '.', ''), '0'), '.') }}</td>
            <td class="num">{{ number_format($precio, 2) }}</td>
            <td class="num">{{ number_format($totalLinea, 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<hr>

<table class="lines">
    <tr>
        <td>Subtotal</td>
        <td class="val">{{ $moneda }} {{ number_format((float) ($venta->sub_total ?? 0), 2) }}</td>
    </tr>
    <tr>
        <td>Exento</td>
        <td class="val">{{ $moneda }} {{ number_format((float) ($venta->exenta ?? 0), 2) }}</td>
    </tr>
    @if ($esHonduras && $totalesHn)
        <tr>
            <td>Gravado 15% ISV</td>
            <td class="val">{{ $moneda }} {{ number_format($totalesHn['gravado_15'], 2) }}</td>
        </tr>
        <tr>
            <td>Gravado 18% ISV</td>
            <td class="val">{{ $moneda }} {{ number_format($totalesHn['gravado_18'], 2) }}</td>
        </tr>
        <tr>
            <td>ISV 15%</td>
            <td class="val">{{ $moneda }} {{ number_format($totalesHn['isv_15'], 2) }}</td>
        </tr>
        <tr>
            <td>ISV 18%</td>
            <td class="val">{{ $moneda }} {{ number_format($totalesHn['isv_18'], 2) }}</td>
        </tr>
    @else
        <tr>
            <td>{{ $etiquetaImpuesto }}</td>
            <td class="val">{{ $moneda }} {{ number_format((float) ($venta->iva ?? 0), 2) }}</td>
        </tr>
    @endif
    <tr>
        <td>Descuento</td>
        <td class="val">{{ $moneda }} {{ number_format((float) ($venta->descuento ?? 0), 2) }}</td>
    </tr>
    <tr>
        <td class="b">Total</td>
        <td class="val b">{{ $moneda }} {{ number_format((float) $venta->total, 2) }}</td>
    </tr>
</table>

<div class="sec">
    <p class="b">Total en letras</p>
    @if ($esHonduras)
        <p>{{ strtoupper($letras) }} LEMPIRAS {{ $centavos }}/100</p>
    @else
        <p>{{ strtoupper($letras) }} {{ $moneda }} {{ $centavos }}/100</p>
    @endif
</div>

@if ($esHonduras)
    <div class="sec">
        @if ($cai !== '')
            <p class="b">CAI</p>
            <p>{{ $cai }}</p>
        @endif
        @if ($rango !== '')
            <p class="b">Rango autorizado</p>
            <p>{{ $rango }}</p>
        @endif
        @if ($fechaLimite !== '')
            <p class="b">Fecha límite de emisión</p>
            <p>{{ $fechaLimite }}</p>
        @endif
        <p class="cen b">La factura es beneficio de todos, exíjala</p>
    </div>
@endif

@if ($obs !== '')
    <div class="sec">
        <p class="b">Observaciones</p>
        <p>{!! nl2br(e($obs)) !!}</p>
    </div>
@endif

<p class="foot">Documento generado por SmartPyme</p>
<p class="foot">Fecha de impresión: {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}</p>

@if (empty($venta->pdf))
<script>window.onload = function () { window.print(); };</script>
@endif
</body>
</html>
