<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $documento->nombre }} - {{ $venta->correlativo }}</title>
    <style>
        @page { size: letter portrait; margin: 1.2cm 1.4cm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111; font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.25; }
        h1, h2, p { margin: 0; }
        h1 { font-size: 15px; }
        h2 { font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 3px 4px; vertical-align: top; }
        .sin-borde, .sin-borde td { border: 0; }
        .borde th, .borde td { border: 1px solid #222; }
        .encabezado { margin-bottom: 8px; }
        .logo { max-height: 65px; max-width: 190px; }
        .documento { text-align: right; }
        .numero { margin-top: 3px; color: #a00; font-size: 13px; font-weight: bold; }
        .datos { margin-bottom: 7px; }
        .datos td { width: 50%; border: 1px solid #222; }
        .referencias { margin-bottom: 7px; table-layout: fixed; }
        .referencias th { background: #eee; font-size: 8px; text-align: center; }
        .referencias td { text-align: center; }
        .detalle { margin-bottom: 7px; }
        .detalle thead { display: table-row-group; }
        .detalle th { background: #eee; text-align: center; }
        .detalle tr { page-break-inside: avoid; }
        .numero-columna { text-align: right; white-space: nowrap; }
        .resumen { page-break-inside: avoid; }
        .resumen .letras { width: 64%; }
        .resumen .etiqueta { width: 23%; text-align: right; }
        .resumen .monto { width: 13%; text-align: right; white-space: nowrap; }
        .nota { margin-top: 9px; text-align: justify; }
        .footer { margin-top: 8px; text-align: center; page-break-inside: avoid; }
        .footer p { margin-top: 2px; }
        .leyendas { margin-top: 9px; font-weight: bold; }
    </style>
</head>
<body>
@php
    $detalles = $venta->relationLoaded('detalles')
        ? $venta->getRelation('detalles')
        : collect();
    $sucursal = $venta->relationLoaded('sucursal')
        ? $venta->getRelation('sucursal')
        : null;
    $ivaEmpresa = (float) ($empresa->iva ?? 15);
    $totales = \App\Support\Honduras\DocumentoImpresionHn::totales($detalles, $ivaEmpresa);
    $footer = \App\Support\Honduras\DocumentoImpresionHn::footer($documento);
    $correlativo = \App\Support\Honduras\DocumentoImpresionHn::correlativo($documento, $venta->correlativo);

    $logo = trim((string) ($empresa->logo ?? ''));
    $direccionEmpresa = trim((string) ($sucursal->direccion ?? $empresa->direccion ?? ''));
    $telefonoEmpresa = trim((string) ($sucursal->telefono ?? $empresa->telefono ?? ''));
    $correoEmpresa = trim((string) ($sucursal->correo ?? $empresa->correo ?? ''));

    $nombreCliente = 'Consumidor Final';
    $rtnCliente = '';
    $direccionCliente = '';
    $telefonoCliente = '';
    if ($cliente) {
        $nombreCliente = $cliente->tipo === 'Empresa'
            ? trim((string) ($cliente->nombre_empresa ?? ''))
            : trim((string) (($cliente->nombre ?? '').' '.($cliente->apellido ?? '')));
        $nombreCliente = $nombreCliente !== '' ? $nombreCliente : 'Consumidor Final';
        $rtnCliente = trim((string) ($cliente->nit ?? $cliente->dui ?? ''));
        $direccionCliente = trim((string) (
            $cliente->tipo === 'Empresa'
                ? ($cliente->empresa_direccion ?? $cliente->direccion ?? '')
                : ($cliente->direccion ?? '')
        ));
        $telefonoCliente = trim((string) (
            $cliente->tipo === 'Empresa'
                ? ($cliente->empresa_telefono ?? $cliente->telefono ?? '')
                : ($cliente->telefono ?? '')
        ));
    }

    $ordenExenta = trim((string) ($venta->num_orden_exento ?? ''));
    $constanciaExoneracion = trim((string) ($cliente?->ncr ?? ''));
    $registroSag = trim((string) (
        $venta->getAttribute('registro_sag')
            ?? $venta->getAttribute('num_registro_sag')
            ?? $cliente?->getAttribute('registro_sag')
            ?? ''
    ));
    $fechaVenta = $venta->fecha
        ? \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y')
        : '';
@endphp

<table class="encabezado sin-borde">
    <tr>
        <td style="width: 58%;">
            @if ($logo !== '')
                <img class="logo" src="{{ public_path('img/'.ltrim($logo, '/')) }}" alt="Logo">
            @endif
            <h2>{{ mb_strtoupper((string) $empresa->nombre, 'UTF-8') }}</h2>
            @if ($empresa->nit)<p><strong>RTN:</strong> {{ $empresa->nit }}</p>@endif
            @if ($direccionEmpresa !== '')<p>{{ $direccionEmpresa }}</p>@endif
            @if ($telefonoEmpresa !== '')<p><strong>Teléfono:</strong> {{ $telefonoEmpresa }}</p>@endif
            @if ($correoEmpresa !== '')<p><strong>E-mail:</strong> {{ $correoEmpresa }}</p>@endif
        </td>
        <td class="documento" style="width: 42%;">
            <h1>{{ mb_strtoupper((string) $documento->nombre, 'UTF-8') }}</h1>
            <p class="numero">{{ $correlativo }}</p>
            @if ($fechaVenta !== '')<p><strong>Fecha:</strong> {{ $fechaVenta }}</p>@endif
            @if ($venta->condicion)<p><strong>Condición:</strong> {{ $venta->condicion }}</p>@endif
            @if ($venta->forma_pago)<p><strong>Método de pago:</strong> {{ $venta->forma_pago }}</p>@endif
        </td>
    </tr>
</table>

<table class="datos">
    <tr>
        <td>
            <strong>Cliente:</strong> {{ $nombreCliente }}<br>
            @if ($rtnCliente !== '')<strong>RTN:</strong> {{ $rtnCliente }}<br>@endif
            @if ($direccionCliente !== '')<strong>Dirección:</strong> {{ $direccionCliente }}<br>@endif
            @if ($telefonoCliente !== '')<strong>Teléfono:</strong> {{ $telefonoCliente }}@endif
        </td>
        <td>
            @if ($venta->num_cotizacion)<strong>Cotización:</strong> {{ $venta->num_cotizacion }}<br>@endif
            @if ($venta->num_orden)<strong>Orden:</strong> {{ $venta->num_orden }}<br>@endif
            @if ($venta->observaciones)<strong>Observaciones:</strong> {{ $venta->observaciones }}@endif
        </td>
    </tr>
</table>

@if ($ordenExenta !== '' || $constanciaExoneracion !== '' || $registroSag !== '')
    <table class="referencias borde">
        <thead>
            <tr>
                <th>ORDEN DE COMPRA EXENTA</th>
                <th>CONSTANCIA DE EXONERACIÓN</th>
                <th>REGISTRO SAG</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $ordenExenta }}</td>
                <td>{{ $constanciaExoneracion }}</td>
                <td>{{ $registroSag }}</td>
            </tr>
        </tbody>
    </table>
@endif

<table class="detalle borde">
    <thead>
        <tr>
            <th style="width: 10%;">Cantidad</th>
            <th style="width: 18%;">Código</th>
            <th>Descripción</th>
            <th style="width: 16%;">Precio unitario</th>
            <th style="width: 16%;">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($detalles as $detalle)
            @php
                $producto = $detalle->relationLoaded('producto')
                    ? $detalle->getRelation('producto')
                    : null;
                $descripcion = trim((string) ($detalle->getAttributes()['descripcion'] ?? ''));
                if ($descripcion === '' && $producto) {
                    $descripcion = trim((string) ($producto->nombre ?? ''));
                }
                $codigo = $producto ? trim((string) ($producto->codigo ?? $producto->barcode ?? '')) : '';
            @endphp
            <tr>
                <td class="numero-columna">{{ number_format((float) $detalle->cantidad, 2) }}</td>
                <td>{{ $codigo }}</td>
                <td>{{ $descripcion }}</td>
                <td class="numero-columna">L {{ number_format((float) $detalle->precio, 2) }}</td>
                <td class="numero-columna">L {{ number_format((float) $detalle->total, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="resumen borde">
    <tr>
        <td class="letras" rowspan="8">
            <strong>Total en letras:</strong><br>
            {{ mb_strtoupper((string) $dolares, 'UTF-8') }} CON {{ $centavos }}/100 LEMPIRAS
        </td>
        <td class="etiqueta">Importe Exonerado:</td>
        <td class="monto">L {{ number_format($totales['exonerado'], 2) }}</td>
    </tr>
    <tr><td class="etiqueta">Importe Exento:</td><td class="monto">L {{ number_format($totales['exento'], 2) }}</td></tr>
    <tr><td class="etiqueta">Importe Gravado 15%:</td><td class="monto">L {{ number_format($totales['gravado_15'], 2) }}</td></tr>
    <tr><td class="etiqueta">Importe Gravado 18%:</td><td class="monto">L {{ number_format($totales['gravado_18'], 2) }}</td></tr>
    <tr><td class="etiqueta">ISV 15%:</td><td class="monto">L {{ number_format($totales['isv_15'], 2) }}</td></tr>
    <tr><td class="etiqueta">ISV 18%:</td><td class="monto">L {{ number_format($totales['isv_18'], 2) }}</td></tr>
    <tr><td class="etiqueta">Descuentos y rebajas:</td><td class="monto">L {{ number_format($totales['descuento'], 2) }}</td></tr>
    <tr><td class="etiqueta"><strong>TOTAL A PAGAR:</strong></td><td class="monto"><strong>L {{ number_format((float) $venta->total, 2) }}</strong></td></tr>
</table>

@if ($footer['nota'])
    <div class="nota">{!! nl2br(e($footer['nota'])) !!}</div>
@endif

<div class="footer">
    @if ($footer['cai'])<p><strong>CAI:</strong> {{ $footer['cai'] }}</p>@endif
    @if ($footer['rango'])<p><strong>RANGO AUTORIZADO:</strong> {{ $footer['rango'] }}</p>@endif
    @if ($footer['fecha_limite'])<p><strong>FECHA LÍMITE DE EMISIÓN:</strong> {{ $footer['fecha_limite'] }}</p>@endif
    <div class="leyendas">
        <p>Original: Cliente &nbsp;&nbsp; Copia: Emisor</p>
        <p>La Factura es Beneficio de Todos, EXÍJALA</p>
    </div>
</div>
</body>
</html>
