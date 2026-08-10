<!DOCTYPE html>
<html>
<head>
    <title>{{ $empresa->nombre }} {{ $venta->nombre_documento }} - {{ $venta->correlativo }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            line-height: 1.25;
            color: #000;
        }
        @page {
            margin: 1.2cm 1.4cm 1.2cm 1.4cm;
            size: letter;
        }
        #factura { width: 100%; }
        p { margin: 0 0 2px 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }

        .logo { max-height: 72px; width: auto; display: block; }
        .num-factura {
            color: #c00;
            font-size: 13px;
            font-weight: bold;
            text-align: right;
        }
        .label-num {
            text-align: right;
            font-size: 10px;
            margin-bottom: 2px;
        }
        .empresa-datos { font-size: 9px; line-height: 1.3; }
        .razon-social {
            margin: 8px 0 6px 0;
            font-size: 11px;
            font-weight: bold;
        }
        .meta td { padding: 1px 4px 1px 0; font-size: 10px; }
        .meta .col-izq { width: 48%; }
        .meta .col-der { width: 52%; }
        .fiscal { margin: 6px 0 8px 0; font-size: 10px; }
        .fiscal p { margin: 1px 0; }

        #productos { margin-top: 4px; }
        #productos th {
            border: 1px solid #000;
            background: #f0f0f0;
            font-size: 9px;
            padding: 4px 3px;
            text-align: left;
        }
        #productos td {
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #ccc;
            padding: 3px;
            font-size: 9px;
        }
        #productos tbody tr:last-child td { border-bottom: 1px solid #000; }
        .col-prod { width: 42%; }
        .col-cant { width: 12%; text-align: right; }
        .col-precio { width: 15%; text-align: right; }
        .col-desc { width: 15%; text-align: right; }
        .col-sub { width: 16%; text-align: right; }
        .right { text-align: right; }

        .bloque-totales { margin-top: 6px; }
        .bloque-totales .obs { width: 48%; font-size: 9px; padding-right: 8px; }
        .bloque-totales .totales { width: 52%; }
        .totales table td {
            padding: 2px 3px;
            font-size: 9px;
            border-bottom: 1px solid #ddd;
        }
        .totales .lbl { text-align: left; }
        .totales .val { text-align: right; width: 32%; white-space: nowrap; }
        .totales .total-row td {
            font-weight: bold;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding-top: 4px;
            padding-bottom: 4px;
        }
        .letras {
            margin-top: 8px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .ordenes { margin-top: 8px; font-size: 9px; }
        .ordenes p { margin: 2px 0; }
        .formas {
            margin-top: 10px;
            border-top: 1px solid #000;
            padding-top: 6px;
            font-size: 9px;
            line-height: 1.35;
        }
        .formas h4 {
            font-size: 10px;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .formas .cuentas { margin: 4px 0 6px 12px; }
        .cierre {
            margin-top: 10px;
            text-align: center;
            font-size: 10px;
            font-weight: bold;
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
@endphp

    {{-- Encabezado --}}
    <table>
        <tr>
            <td style="width: 58%;">
                @if ($venta->empresa()->pluck('logo')->first())
                    <img class="logo" src="{{ asset('img/'.$venta->empresa()->pluck('logo')->first()) }}" alt="Logo">
                @endif
                <div class="empresa-datos" style="margin-top: 4px;">
                    @if ($direccionFactura)<p>{{ $direccionFactura }}</p>@endif
                    @if ($empresa->nit)<p>RTN# {{ $empresa->nit }}</p>@endif
                    @if ($telefonoFactura)<p>{{ $telefonoFactura }}</p>@endif
                    @if ($correoFactura)<p>{{ $correoFactura }}</p>@endif
                </div>
            </td>
            <td style="width: 42%;">
                <p class="label-num">Número de factura</p>
                <p class="num-factura">{{ $numFacturaDisplay }}</p>
            </td>
        </tr>
    </table>

    <p class="razon-social">RAZÓN SOCIAL: {{ strtoupper($empresa->nombre) }}</p>

    <table class="meta">
        <tr>
            <td class="col-izq">
                <p><b>FECHA:</b> {{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
                <p><b>ORIGINAL:</b> Cliente</p>
                <p><b>COPIA:</b> Obligado Tributario Emisor</p>
                <p><b>VENDEDOR:</b> {{ $vendedorNombre }}</p>
            </td>
            <td class="col-der">
                <p><b>CLIENTE:</b> {{ strtoupper($venta->nombre_cliente ?? '') }}</p>
                <p><b>TELÉFONO:</b> {{ $telefonoClienteFactura }}</p>
                <p><b>CORREO:</b> {{ $correoClienteFactura }}</p>
                <p><b>RTN:</b> {{ $rtnCliente }}</p>
                <p><b>DIRECCIÓN:</b> {{ $direccionClienteFactura }}</p>
            </td>
        </tr>
    </table>

    <div class="fiscal">
        @if ($cai)
            <p><b>CAI:</b> {{ $cai }}</p>
        @endif
        @if ($fechaLimiteFmt)
            <p><b>FECHA LÍMITE:</b> {{ $fechaLimiteFmt }}</p>
        @endif
        @if ($rangoAuth)
            <p><b>RANGO AUTORIZADO:</b> {{ $rangoAuth }}</p>
        @endif
    </div>

    <table id="productos">
        <thead>
            <tr>
                <th class="col-prod">Producto</th>
                <th class="col-cant">Cantidad</th>
                <th class="col-precio">Precio</th>
                <th class="col-desc">Descuento</th>
                <th class="col-sub">Subtotal</th>
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
                    <td>{{ $detalle->nombre_producto }}</td>
                    <td class="right">{{ number_format($cant, 2) }}</td>
                    <td class="right">L{{ number_format($precio, 2) }}</td>
                    <td class="right">L{{ number_format($desc, 2) }}</td>
                    <td class="right">L{{ number_format($subLinea, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="bloque-totales">
        <tr>
            <td class="obs">
                <p><b>Observaciones:</b> {{ $venta->observaciones ?? '' }}</p>
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
                    <tr class="total-row">
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

    <div class="ordenes">
        <p><b># ORDEN DE COMPRA:</b> {{ $venta->num_orden ?? '' }}</p>
        <p><b>N° CORRELATIVO DE ORDEN DE COMPRA EXENTA:</b> {{ $venta->num_orden_exento ?? '' }}</p>
        <p><b>N° CORRELATIVO DE CONSTANCIA DE REGISTRO EXONERADO:</b> {{ ($venta->id_cliente && isset($cliente) && $cliente) ? ($cliente->ncr ?? '') : '' }}</p>
        <p><b>N° IDENTIFICATIVO DEL REGISTRO DE LA SAG:</b></p>
    </div>

    <div class="formas">
        <h4>FORMAS DE PAGOS</h4>
        <p>Transferencias bancarias cuentas a nombre de INVERSIONES PADILLA ALVARADO S DE RL:</p>
        <div class="cuentas">
            <p>BAC: 750524201 ahorro</p>
            <p>BAC: 748185231 Dólares</p>
            <p>FICOHSA: 200015533217 ahorro</p>
            <p>FICOHSA: 200015533292 Dólares</p>
        </div>
        <p>Si el pago se realiza en lempiras, se debe realizar a tasa de cambio L. 26.9455</p>
        <p>Se debe enviar comprobante de pago al correo: sac@bigtechnologyhn.com</p>
        <p>Si realiza retención debe compartirlo con su comprobante de pago</p>
        <p>Fecha de vencimiento se detalla en factura.</p>
        <p>Cargo moratorio del 5% al no realizar su pago puntual</p>
    </div>

    <p class="cierre">La factura es beneficio de todos, ¡exíjala!</p>
</section>
</body>
</html>
