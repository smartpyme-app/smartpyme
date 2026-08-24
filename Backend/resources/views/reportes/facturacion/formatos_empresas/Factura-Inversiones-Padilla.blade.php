<!DOCTYPE html>
<html>
<head>
    <title>{{ $empresa->nombre }} {{ $documento->nombre ?? $venta->nombre_documento }} - {{ $venta->correlativo }}</title>
    <style>
        /* Mismo patrón que Accesorios-HN / Lilian / Inversiones-Andre:
           @page + padding en #factura (DomPDF no confía solo en @page). */
        * { margin: 0; padding: 0; }
        html, body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            line-height: 1.3;
            color: #222;
        }
        @page {
            margin: 2cm 2.5cm;
            margin-top: 2cm;
            margin-bottom: 2cm;
            margin-left: 2.5cm;
            margin-right: 2.5cm;
            size: letter;
        }
        #factura {
            padding: 50px;
        }
        p { margin: 0 0 2px 0; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th { vertical-align: top; word-wrap: break-word; }

        .logo { height: 100px; width: auto; }
        .header-right { text-align: right; font-size: 9px; line-height: 1.35; }
        .header-right .num-linea {
            font-size: 10.5px;
            font-weight: bold;
            color: #111;
            margin-bottom: 3px;
        }

        .meta { margin-top: 12px; margin-bottom: 8px; }
        .meta td { font-size: 8.5px; line-height: 1.35; padding-right: 6px; }
        .meta .col-a { width: 28%; }
        .meta .col-b { width: 36%; }
        .meta .col-c { width: 24%; }
        .meta .col-qr { width: 12%; text-align: center; padding-right: 0; }
        .meta b { color: #111; }
        .qr-img { width: 48px; height: 48px; }

        #productos { margin-top: 2px; }
        #productos thead th {
            background: #e8e8e8;
            border-bottom: 1px solid #bbb;
            font-size: 8.5px;
            font-weight: bold;
            padding: 5px 3px;
            text-align: left;
        }
        #productos thead th.num { text-align: right; }
        #productos tbody td {
            border-bottom: 1px solid #eee;
            padding: 5px 3px;
            font-size: 9px;
        }
        #productos .col-prod { width: 42%; }
        #productos .col-cant { width: 13%; text-align: right; }
        #productos .col-precio { width: 15%; text-align: right; }
        #productos .col-desc { width: 15%; text-align: right; }
        #productos .col-sub { width: 15%; text-align: right; }

        .wrap-totales { margin-top: 2px; }
        .wrap-totales .obs { width: 48%; font-size: 8px; color: #444; padding-right: 8px; }
        .wrap-totales .totales { width: 52%; }
        .totales td { padding: 1px 0; font-size: 8.5px; }
        .totales .lbl { text-align: left; padding-right: 6px; }
        .totales .val { text-align: right; width: 26%; }
        .totales .strong td { font-weight: bold; color: #111; }

        .letras {
            margin-top: 12px;
            text-align: center;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .ordenes { margin-top: 10px; }
        .ordenes td { width: 50%; padding: 2px 6px 2px 0; font-size: 8.5px; }

        .formas { margin-top: 12px; font-size: 8.5px; line-height: 1.4; color: #333; }
        .formas h4 { font-size: 9.5px; margin-bottom: 3px; color: #111; }
        .formas ul { margin: 0 0 0 14px; padding: 0; }
        .formas ul ul { margin: 2px 0 3px 14px; list-style-type: disc; }
        .formas li { margin: 1px 0; }
        .formas a { color: #1565c0; text-decoration: underline; }
        .formas .moratorio { font-weight: bold; color: #111; }

        .cierre {
            margin-top: 14px;
            text-align: center;
            font-size: 9.5px;
            color: #333;
        }
    </style>
</head>
<body>
<section id="factura">
@php
    // El UI no captura `prefijo`: sucursal → documento.prefijo → rango (Serie o Autorización).
    $corr = str_pad((string) $venta->correlativo, 8, '0', STR_PAD_LEFT);
    $prefPorSucursalJson = data_get($empresa->custom_empresa, 'configuraciones.prefijo_factura_padilla_por_sucursal', []);
    $prefPorSucursal = is_array($prefPorSucursalJson) ? $prefPorSucursalJson : [];
    $idSucVenta = $venta->id_sucursal;
    $pref = '';
    if ($idSucVenta !== null) {
        $prefFijoSucursal = $prefPorSucursal[(string) $idSucVenta] ?? $prefPorSucursal[$idSucVenta] ?? null;
        if ($prefFijoSucursal !== null && trim((string) $prefFijoSucursal) !== '') {
            $pref = trim((string) $prefFijoSucursal);
        }
    }
    if ($pref === '') {
        $pref = trim((string) ($documento->prefijo ?? ''));
    }
    $rangoAuth = data_get($empresa->custom_empresa, 'configuraciones.factura_rango_autorizado')
        ?: (trim((string) ($documento->rangos ?? '')) !== '' ? $documento->rangos : null)
        ?: (trim((string) ($documento->numero_autorizacion ?? '')) !== '' ? $documento->numero_autorizacion : null);
    if ($pref === '' && $rangoAuth) {
        if (preg_match('/(\d{3}-\d{3}-\d{2}-)/', (string) $rangoAuth, $mPref)) {
            $pref = $mPref[1];
        }
    }
    $numFacturaDisplay = $pref !== '' ? rtrim($pref, '-') . '-' . $corr : $corr;

    $cai = data_get($empresa->custom_empresa, 'configuraciones.factura_cai') ?: ($documento->resolucion ?? null);
    $fechaLimiteCai = data_get($empresa->custom_empresa, 'configuraciones.factura_fecha_limite');
    if ($fechaLimiteCai) {
        try {
            $fechaLimiteFmt = \Carbon\Carbon::parse($fechaLimiteCai)->format('d/m/Y');
        } catch (\Throwable $e) {
            $fechaLimiteFmt = $fechaLimiteCai;
        }
    } else {
        $fechaLimiteFmt = !empty($documento->fecha)
            ? \Carbon\Carbon::parse($documento->fecha)->format('d/m/Y')
            : '';
    }

    $sucursalVenta = $venta->sucursal ?? null;
    $telefonoFactura = ($sucursalVenta && trim((string) ($sucursalVenta->telefono ?? '')) !== '')
        ? $sucursalVenta->telefono
        : ($empresa->telefono ?? null);
    $direccionFactura = ($sucursalVenta && trim((string) ($sucursalVenta->direccion ?? '')) !== '')
        ? $sucursalVenta->direccion
        : ($empresa->direccion ?? null);
    $correoFactura = ($sucursalVenta && trim((string) ($sucursalVenta->correo ?? '')) !== '')
        ? $sucursalVenta->correo
        : ($empresa->correo ?? $empresa->email ?? null);

    $direccionClienteFactura = '';
    $telefonoClienteFactura = '';
    $correoClienteFactura = '';
    $rtnCliente = '';
    if ($venta->id_cliente && isset($cliente) && $cliente) {
        $baseDir = trim((string) ($cliente->getDireccionEfectiva() ?? ''));
        if ($baseDir === '') {
            $baseDir = trim((string) ($cliente->direccion ?? $cliente->empresa_direccion ?? ''));
        }
        $partesDir = array_filter([
            $baseDir !== '' ? $baseDir : null,
            trim((string) ($cliente->municipio ?? '')) ?: null,
            trim((string) ($cliente->departamento ?? '')) ?: null,
        ]);
        $direccionClienteFactura = trim(implode(', ', $partesDir));
        $telefonoClienteFactura = trim((string) ($cliente->getTelefonoEfectivo() ?? $cliente->telefono ?? $cliente->empresa_telefono ?? ''));
        $correoClienteFactura = trim((string) ($cliente->correo ?? $cliente->email ?? ''));
        $rtnCliente = trim((string) ($cliente->ncr ?? ''));
        if ($rtnCliente === '') {
            $rtnCliente = trim((string) ($cliente->nit ?? ''));
        }
        if ($rtnCliente === '') {
            $rtnCliente = trim((string) ($cliente->dui ?? ''));
        }
    }

    $vendedorNombre = '';
    if ($venta->relationLoaded('vendedor') && $venta->vendedor) {
        $vendedorNombre = trim((string) $venta->vendedor->name);
    } elseif (!empty($venta->id_vendedor)) {
        $vendedorNombre = trim((string) ($venta->nombre_vendedor ?? ''));
    }

    $ivaEmpresa = (float) ($empresa->iva ?? ($venta->relationLoaded('empresa') ? optional($venta->empresa)->iva : null) ?? 15);
    $iva_15 = 0.0;
    $gravada_15 = 0.0;
    $importe_exento = 0.0;
    $importe_exonerado = 0.0;
    $descuentoLineas = 0.0;

    foreach ($venta->detalles as $det) {
        $descuentoLineas += (float) ($det->descuento ?? 0);
        $porc = $det->porcentaje_impuesto !== null && $det->porcentaje_impuesto !== ''
            ? (float) $det->porcentaje_impuesto
            : $ivaEmpresa;
        $tipoGrav = strtolower(trim((string) ($det->tipo_gravado ?? 'gravada')));

        if ($tipoGrav === 'exonerada' || $tipoGrav === 'exonerado') {
            $montoExo = (float) ($det->exenta ?? $det->gravada ?? $det->sub_total ?? 0);
            $importe_exonerado += $montoExo;
            continue;
        }

        if (abs($porc) < 0.01 || $tipoGrav === 'exenta' || $tipoGrav === 'exento') {
            $montoExento = (float) ($det->exenta ?? 0);
            if ($montoExento <= 0) {
                $montoExento = (float) ($det->gravada ?? $det->sub_total ?? $det->total ?? 0);
            }
            $importe_exento += $montoExento;
            continue;
        }

        $g = (float) ($det->gravada ?? $det->sub_total ?? 0);
        $ivaDet = (float) ($det->iva ?? 0);
        if ($ivaDet < 0.0001 && $g > 0.0001) {
            $ivaDet = round($g * ($porc / 100), 2);
        }
        $iva_15 += $ivaDet;
        $gravada_15 += $g;
    }

    $ivaCabecera = (float) ($venta->iva ?? 0);
    if ($ivaCabecera > 0.0001 && $iva_15 < 0.005) {
        $iva_15 = round($ivaCabecera, 2);
    } elseif ($ivaCabecera > 0.0001 && abs($ivaCabecera - $iva_15) > 0.02 && abs($ivaCabecera - $iva_15) < 0.5) {
        $iva_15 = round($ivaCabecera, 2);
    }

    $subTotal = (float) ($venta->sub_total ?? ($gravada_15 + $importe_exento + $importe_exonerado));
    $descReb = (float) ($venta->descuento ?? $descuentoLineas);
    $formaPagoLabel = trim((string) ($venta->forma_pago ?: $venta->condicion ?: 'Transferencia bancaria'));
    $cambio = (float) ($venta->cambio ?? 0);
    // Multimoneda HN: total en HNL; si currency_code=USD, DLLS = total / exchange_rate.
    $currencyCode = strtoupper(trim((string) ($venta->currency_code ?? 'HNL')));
    $tasaCambio = (float) ($venta->exchange_rate ?? 0);
    $mostrarUsd = $currencyCode === 'USD' && $tasaCambio > 0 && abs($tasaCambio - 1.0) > 0.00001;
    $totalUsd = $mostrarUsd ? round((float) $venta->total / $tasaCambio, 2) : null;
    $tasaCambioFmt = $mostrarUsd ? rtrim(rtrim(number_format($tasaCambio, 5, '.', ''), '0'), '.') : null;
    $totalLetras = strtoupper(trim((string) $dolares) . ' LEMPIRAS CON ' . trim((string) $centavos) . ' CENTAVOS');
    $observaciones = trim((string) ($venta->observaciones ?? ''));

    $rtnEmpresa = trim((string) ($empresa->nit ?? ''));
    if ($rtnEmpresa === '') {
        $rtnEmpresa = trim((string) ($empresa->ncr ?? ''));
    }

    $qrPayload = trim(implode('|', array_filter([
        (string) $numFacturaDisplay,
        (string) ($cai ?? ''),
        (string) $rtnEmpresa,
        number_format((float) $venta->total, 2, '.', ''),
    ])));
@endphp

    <table>
        <tr>
            <td style="width: 38%;">
                @php
                    $logoPath = $empresa->logo ?? ($venta->relationLoaded('empresa') ? optional($venta->empresa)->logo : null);
                @endphp
                @if ($logoPath)
                    <img class="logo" src="{{ asset('img/'.$logoPath) }}" alt="Logo">
                @endif
            </td>
            <td class="header-right" style="width: 62%;">
                <p class="num-linea">Número de factura {{ $numFacturaDisplay }}</p>
                @if ($direccionFactura)<p>{{ $direccionFactura }}</p>@endif
                @if ($rtnEmpresa !== '')<p>RTN# {{ $rtnEmpresa }}</p>@endif
                @if ($telefonoFactura)<p>{{ $telefonoFactura }}</p>@endif
                @if ($correoFactura)<p>{{ $correoFactura }}</p>@endif
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td class="col-a">
                <p><b>RAZÓN SOCIAL:</b> {{ $empresa->nombre }}</p>
                <p><b>FECHA:</b> {{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
                <p><b>ORIGINAL:</b> Cliente</p>
                <p><b>COPIA:</b> Obligado Tributario Emisor</p>
                <p><b>VENDEDOR:</b> {{ $vendedorNombre }}</p>
            </td>
            <td class="col-b">
                <p><b>CLIENTE:</b> {{ strtoupper($venta->nombre_cliente ?? '') }}</p>
                <p><b>TELÉFONO:</b> {{ $telefonoClienteFactura }}</p>
                <p><b>CORREO:</b> {{ $correoClienteFactura }}</p>
                <p><b>RTN:</b> {{ $rtnCliente }}</p>
                <p><b>DIRECCIÓN:</b> {{ $direccionClienteFactura }}</p>
            </td>
            <td class="col-c">
                @if ($cai)
                    <p><b>CAI:</b> {{ $cai }}</p>
                @endif
                @if ($fechaLimiteFmt)
                    <p><b>FECHA LÍMITE:</b> {{ $fechaLimiteFmt }}</p>
                @endif
                @if ($rangoAuth)
                    <p><b>RANGO AUTORIZADO:</b> {{ $rangoAuth }}</p>
                @endif
            </td>
            <td class="col-qr">
                @if ($qrPayload !== '')
                    <img class="qr-img" src="data:image/png;base64,{{ DNS2D::getBarcodePNG($qrPayload, 'QRCODE', 3, 3) }}" alt="QR">
                @endif
            </td>
        </tr>
    </table>

    <table id="productos">
        <thead>
            <tr>
                <th class="col-prod">Producto</th>
                <th class="col-cant num">Cantidad</th>
                <th class="col-precio num">Precio</th>
                <th class="col-desc num">Descuento</th>
                <th class="col-sub num">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($venta->detalles as $detalle)
                @php
                    $cant = (float) $detalle->cantidad;
                    $precio = (float) $detalle->precio;
                    $desc = (float) ($detalle->descuento ?? 0);
                    $subLinea = (float) ($detalle->sub_total ?? $detalle->gravada ?? (($precio * $cant) - $desc));
                @endphp
                <tr>
                    <td class="col-prod">{{ $detalle->nombre_producto }}</td>
                    <td class="col-cant">{{ number_format($cant, 2) }}</td>
                    <td class="col-precio">L{{ number_format($precio, 2) }}</td>
                    <td class="col-desc">L{{ number_format($desc, 2) }}</td>
                    <td class="col-sub">L{{ number_format($subLinea, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="wrap-totales">
        <tr>
            <td class="obs">
                @if ($observaciones !== '')
                    Observaciones: {{ $observaciones }}
                @endif
            </td>
            <td class="totales">
                <table>
                    <tr>
                        <td class="lbl">Subtotal</td>
                        <td class="val">L{{ number_format($subTotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Subtotal gravado 15%</td>
                        <td class="val">L{{ number_format($gravada_15, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Subtotal exonerado</td>
                        <td class="val">L{{ number_format($importe_exonerado, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Subtotal exento</td>
                        <td class="val">L{{ number_format($importe_exento, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Descuentos o rebajas otorgados</td>
                        <td class="val">L{{ number_format($descReb, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Tercera edad</td>
                        <td class="val">L0.00</td>
                    </tr>
                    <tr>
                        <td class="lbl">Cuarta edad</td>
                        <td class="val">L0.00</td>
                    </tr>
                    <tr>
                        <td class="lbl">Impuesto 15%</td>
                        <td class="val">L{{ number_format($iva_15, 2) }}</td>
                    </tr>
                    <tr class="strong">
                        <td class="lbl">TOTAL A PAGAR</td>
                        <td class="val">L{{ number_format($venta->total, 2) }}</td>
                    </tr>
                    @if ($mostrarUsd)
                        <tr>
                            <td class="lbl">DLLS.</td>
                            <td class="val">${{ number_format($totalUsd, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Tasa de cambio</td>
                            <td class="val">L{{ $tasaCambioFmt }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="lbl">{{ $formaPagoLabel }}</td>
                        <td class="val">L{{ number_format($venta->total, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Cambio</td>
                        <td class="val">L{{ number_format($cambio, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p class="letras">{{ $totalLetras }}</p>

    <table class="ordenes">
        <tr>
            <td><b># ORDEN DE COMPRA:</b> {{ $venta->num_orden ?? '' }}</td>
            <td><b>N° CORRELATIVO DE ORDEN DE COMPRA EXENTA:</b> {{ $venta->num_orden_exento ?? '' }}</td>
        </tr>
        <tr>
            <td><b>N° CORRELATIVO DE CONSTANCIA DE REGISTRO EXONERADO:</b> {{ ($venta->id_cliente && isset($cliente) && $cliente) ? ($cliente->ncr ?? '') : '' }}</td>
            <td><b>N° IDENTIFICATIVO DEL REGISTRO DE LA SAG:</b></td>
        </tr>
    </table>

    <div class="formas">
        <h4>FORMAS DE PAGOS</h4>
        <ul>
            <li>
                Transferencias bancarias cuentas a nombre de INVERSIONES PADILLA ALVARADO S DE RL:
                <ul>
                    <li>BAC: 750524201 ahorro</li>
                    <li>BAC: 748185231 Dólares</li>
                    <li>FICOHSA: 200015533217 ahorro</li>
                    <li>FICOHSA: 200015533292 Dólares</li>
                </ul>
            </li>
            @if ($mostrarUsd)
                <li>Si el pago se realiza en lempiras, se debe realizar a tasa de cambio L. {{ $tasaCambioFmt }}</li>
            @endif
            <li>Se debe enviar comprobante de pago al correo: <a href="mailto:sac@bigtechnologyhn.com">sac@bigtechnologyhn.com</a></li>
            <li>Si realiza retención debe compartirlo con su comprobante de pago</li>
            <li>Fecha de vencimiento se detalla en factura.</li>
            <li class="moratorio">Cargo moratorio del 5% al no realizar su pago puntual</li>
        </ul>
    </div>

    <p class="cierre">La factura es beneficio de todos, ¡exíjala!</p>
</section>
</body>
</html>
