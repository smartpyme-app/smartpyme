<!DOCTYPE html>
<html>
<head>
    <title>{{ $empresa->nombre }} {{ $venta->nombre_documento }} - {{ $venta->correlativo }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 9.5px;
            line-height: 1.35;
            color: #222;
        }
        @page {
            margin: 2cm 2cm 2cm 2cm;
            size: letter;
        }
        #factura {
            width: 100%;
            padding: 4px 6px;
        }
        p { margin: 0 0 2px 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }

        .logo { max-height: 78px; width: auto; display: block; }
        .header-right {
            text-align: right;
            font-size: 9.5px;
            line-height: 1.4;
        }
        .header-right .num-linea {
            font-size: 11px;
            font-weight: bold;
            color: #111;
            margin-bottom: 4px;
        }

        .meta {
            margin-top: 14px;
            margin-bottom: 10px;
        }
        .meta td {
            font-size: 9px;
            line-height: 1.4;
            padding: 0 6px 0 0;
        }
        .meta .col-a { width: 32%; }
        .meta .col-b { width: 38%; }
        .meta .col-c { width: 22%; }
        .meta .col-qr { width: 8%; text-align: right; padding-right: 0; }
        .meta b { color: #111; }
        .qr-img { width: 58px; height: 58px; }

        #productos { margin-top: 4px; width: 100%; }
        #productos thead th {
            background: #e8e8e8;
            border: none;
            border-bottom: 1px solid #bbb;
            font-size: 9px;
            font-weight: bold;
            padding: 6px 5px;
            text-align: left;
            color: #222;
        }
        #productos thead th.right { text-align: right; }
        #productos tbody td {
            border: none;
            border-bottom: 1px solid #eee;
            padding: 6px 5px;
            font-size: 9.5px;
            vertical-align: top;
        }
        #productos .col-prod { width: 44%; }
        #productos .col-cant { width: 12%; text-align: right; }
        #productos .col-precio { width: 14%; text-align: right; }
        #productos .col-desc { width: 14%; text-align: right; }
        #productos .col-sub { width: 16%; text-align: right; }

        .wrap-totales { margin-top: 2px; }
        .wrap-totales .obs {
            width: 52%;
            font-size: 8.5px;
            color: #444;
            padding-right: 12px;
            padding-top: 2px;
        }
        .wrap-totales .totales { width: 48%; }
        .totales table { width: 100%; }
        .totales td {
            padding: 1px 0;
            font-size: 9px;
            line-height: 1.35;
        }
        .totales .lbl { text-align: left; padding-right: 10px; }
        .totales .val { text-align: right; white-space: nowrap; width: 30%; }
        .totales .strong td { font-weight: bold; color: #111; }

        .letras {
            margin-top: 14px;
            text-align: center;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        .ordenes {
            margin-top: 12px;
            font-size: 9px;
        }
        .ordenes td {
            width: 50%;
            padding: 2px 8px 2px 0;
            font-size: 9px;
        }

        .formas {
            margin-top: 14px;
            font-size: 9px;
            line-height: 1.45;
            color: #333;
        }
        .formas h4 {
            font-size: 10px;
            margin-bottom: 4px;
            color: #111;
        }
        .formas ul {
            margin: 0 0 0 16px;
            padding: 0;
        }
        .formas ul ul {
            margin: 2px 0 4px 18px;
            list-style-type: disc;
        }
        .formas li { margin: 2px 0; }
        .formas a { color: #1565c0; text-decoration: underline; }
        .formas .moratorio { font-weight: bold; color: #111; }

        .cierre {
            margin-top: 16px;
            text-align: center;
            font-size: 10px;
            color: #333;
        }
    </style>
</head>
<body>
<section id="factura">
@php
    $corr = str_pad((string) $venta->correlativo, 8, '0', STR_PAD_LEFT);
    $prefPorSucursalJson = data_get($empresa->custom_empresa, 'configuraciones.prefijo_factura_padilla_por_sucursal', []);
    $prefPorSucursal = is_array($prefPorSucursalJson) ? $prefPorSucursalJson : [];
    $idSucVenta = $venta->id_sucursal;
    $prefFijoSucursal = null;
    if ($idSucVenta !== null) {
        $prefFijoSucursal = $prefPorSucursal[(string) $idSucVenta] ?? $prefPorSucursal[$idSucVenta] ?? null;
    }
    if ($prefFijoSucursal !== null && trim((string) $prefFijoSucursal) !== '') {
        $numFacturaDisplay = trim((string) $prefFijoSucursal) . $corr;
    } else {
        $prefDoc = trim((string) ($documento->prefijo ?? ''));
        $numFacturaDisplay = $prefDoc !== '' ? $prefDoc . $corr : $corr;
    }

    $cai = data_get($empresa->custom_empresa, 'configuraciones.factura_cai') ?: ($documento->resolucion ?? null);
    $rangoAuth = data_get($empresa->custom_empresa, 'configuraciones.factura_rango_autorizado') ?: ($documento->rangos ?? null);
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
        $rtnCliente = trim((string) ($cliente->nit ?? ''));
        if ($rtnCliente === '') {
            $rtnCliente = trim((string) ($cliente->dui ?? ''));
        }
    }

    $vendedorNombre = trim((string) ($venta->nombre_vendedor ?? ''));
    if ($vendedorNombre === '' && $venta->id_vendedor) {
        $vendedorNombre = trim((string) (optional($venta->vendedor)->name ?? ''));
    }

    $ivaEmpresa = (float) ($venta->empresa()->pluck('iva')->first() ?? 15);
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
    $totalLetras = strtoupper(trim((string) $dolares) . ' LEMPIRAS CON ' . trim((string) $centavos) . ' CENTAVOS');
    $observaciones = trim((string) ($venta->observaciones ?? ''));

    $qrPayload = trim(implode('|', array_filter([
        (string) $numFacturaDisplay,
        (string) ($cai ?? ''),
        (string) ($empresa->nit ?? ''),
        number_format((float) $venta->total, 2, '.', ''),
    ])));
@endphp

    {{-- Header: logo izq | datos empresa + número a la derecha --}}
    <table>
        <tr>
            <td style="width: 42%; vertical-align: middle;">
                @if ($venta->empresa()->pluck('logo')->first())
                    <img class="logo" src="{{ asset('img/'.$venta->empresa()->pluck('logo')->first()) }}" alt="Logo">
                @endif
            </td>
            <td class="header-right" style="width: 58%;">
                <p class="num-linea">Número de factura {{ $numFacturaDisplay }}</p>
                @if ($direccionFactura)<p>{{ $direccionFactura }}</p>@endif
                @if ($empresa->nit)<p>RTN# {{ $empresa->nit }}</p>@endif
                @if ($telefonoFactura)<p>{{ $telefonoFactura }}</p>@endif
                @if ($correoFactura)<p>{{ $correoFactura }}</p>@endif
            </td>
        </tr>
    </table>

    {{-- Meta 3 columnas + QR --}}
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
                    <img class="qr-img" src="data:image/png;base64,{{ DNS2D::getBarcodePNG($qrPayload, 'QRCODE', 4, 4) }}" alt="QR">
                @endif
            </td>
        </tr>
    </table>

    {{-- Productos --}}
    <table id="productos">
        <thead>
            <tr>
                <th class="col-prod">Producto</th>
                <th class="col-cant right">Cantidad</th>
                <th class="col-precio right">Precio</th>
                <th class="col-desc right">Descuento</th>
                <th class="col-sub right">Subtotal</th>
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

    {{-- Observaciones izq + totales der (como PDF) --}}
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
            <li>Si el pago se realiza en lempiras, se debe realizar a tasa de cambio L. 26.9455</li>
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
