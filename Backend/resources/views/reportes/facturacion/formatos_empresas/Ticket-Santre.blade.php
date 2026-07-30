<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    @php
        $docLblMeta = strtoupper(trim((string) ($documento->nombre ?? 'FACTURA')));
        $etiquetaNumero = $docLblMeta === 'TICKET' ? 'TICKET' : ($docLblMeta === 'RECIBO' ? 'RECIBO' : 'FACTURA');
    @endphp
    <title>{{ $empresa->nombre }} — {{ $etiquetaNumero }} {{ $venta->correlativo }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            font-family: "DejaVu Sans", "Helvetica", Arial, sans-serif;
            font-size: 8pt;
            line-height: 1.3;
            color: #000;
        }
        .wrap {
            width: 100%;
            max-width: 72mm;
            margin: 0 auto;
            padding: 2mm 3mm 4mm;
        }
        .cen { text-align: center; }
        .right { text-align: right; }
        .left { text-align: left; }
        .b { font-weight: bold; }
        .up { text-transform: uppercase; }
        .mt1 { margin-top: 2pt; }
        .mt2 { margin-top: 4pt; }
        .sec { font-size: 7.5pt; margin-top: 5pt; }
        .sec-title { font-weight: bold; font-size: 8pt; margin-bottom: 2pt; }
        .muted { font-size: 7pt; }
        hr.d { border: none; border-top: 1px dashed #000; margin: 5pt 0; }
        hr.s { border: none; border-top: 1px solid #000; margin: 4pt 0; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 3pt; }
        table.lines th {
            font-size: 7pt;
            text-align: left;
            border-bottom: 1px solid #000;
            padding: 2pt 1pt;
            font-weight: bold;
        }
        table.lines th.num, table.lines td.num { text-align: right; white-space: nowrap; }
        table.lines td { padding: 2pt 1pt; vertical-align: top; font-size: 7.5pt; }
        .tot-row td { padding: 1pt 0; font-size: 7.5pt; }
        .tot-row .val { text-align: right; white-space: nowrap; }
        .tot-pay td { padding-top: 3pt; font-size: 9pt; }
        .fiscal { font-size: 7pt; margin-top: 4pt; }
        .foot { font-size: 6.5pt; margin-top: 6pt; text-align: center; }
    </style>
</head>
<body>
<div class="wrap">
    @if (empty($venta->pdf))
        <p class="cen no-print">
            <button type="button" onclick="window.print();">Imprimir</button>
            <button type="button" onclick="window.close();">Cerrar</button>
        </p>
    @endif

    @php
        $sucursalVenta = $venta->sucursal ?? $venta->sucursal()->first();
        $telefonoEmpresa = ($sucursalVenta && trim((string) ($sucursalVenta->telefono ?? '')) !== '')
            ? $sucursalVenta->telefono
            : ($empresa->telefono ?? null);
        $direccionCasaMatriz = trim((string) ($empresa->direccion ?? ''));
        $direccionEstablecimiento = ($sucursalVenta && trim((string) ($sucursalVenta->direccion ?? '')) !== '')
            ? $sucursalVenta->direccion
            : $direccionCasaMatriz;
        $correoEmpresa = ($sucursalVenta && trim((string) ($sucursalVenta->correo ?? '')) !== '')
            ? $sucursalVenta->correo
            : ($empresa->correo ?? null);
        $nombreSucursal = ($sucursalVenta && trim((string) ($sucursalVenta->nombre ?? '')) !== '')
            ? $sucursalVenta->nombre
            : 'Principal';

        // Correlativo HN: prefijo + 8 dígitos (p. ej. 001-001-01-00000001).
        // El UI de documentos no captura `prefijo`; Accesorios lo cubre con mapa por sucursal.
        // Aquí: sucursal → documento.prefijo → prefijo extraído del rango autorizado (Serie).
        $corr = str_pad((string) $venta->correlativo, 8, '0', STR_PAD_LEFT);
        $prefPorSucursalJson = data_get($empresa->custom_empresa, 'configuraciones.prefijo_factura_santre_por_sucursal', []);
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
        if ($pref === '') {
            $rangoParaPref = (string) (
                data_get($empresa->custom_empresa, 'configuraciones.factura_rango_autorizado')
                ?: ($documento->rangos ?? '')
            );
            if (preg_match('/(\d{3}-\d{3}-\d{2}-)/', $rangoParaPref, $mPref)) {
                $pref = $mPref[1];
            }
        }
        $numFacturaDisplay = $pref !== '' ? rtrim($pref, '-').'-'.$corr : $corr;

        $fechaEmision = \Carbon\Carbon::parse($venta->fecha);
        $fechaEmisionFmt = $fechaEmision->locale('es')->isoFormat('D [de] MMMM [de] YYYY HH:mm');
        $metodoPago = trim((string) ($venta->forma_pago ?: $venta->condicion ?: ''));
        $nombreCliente = trim((string) ($venta->nombre_cliente ?? '')) !== ''
            ? $venta->nombre_cliente
            : 'Consumidor final';
        $codCliente = $venta->id_cliente && $cliente && $cliente->codigo_cliente !== null && $cliente->codigo_cliente !== ''
            ? $cliente->codigo_cliente
            : '0';
        $rtnCliente = '';
        if ($venta->id_cliente && $cliente) {
            $rtnCliente = trim((string) ($cliente->nit ?? ''));
            if ($rtnCliente === '') {
                $rtnCliente = trim((string) ($cliente->dui ?? ''));
            }
        }

        $ivaEmpresa = (float) ($venta->empresa()->pluck('iva')->first() ?? 18);
        $iva_15 = 0.0;
        $iva_18 = 0.0;
        $gravada_15 = 0.0;
        $gravada_18 = 0.0;

        foreach ($venta->detalles as $det) {
            $porc = $det->porcentaje_impuesto !== null && $det->porcentaje_impuesto !== ''
                ? (float) $det->porcentaje_impuesto
                : $ivaEmpresa;
            $g = (float) ($det->gravada ?? $det->sub_total ?? 0);
            $ivaDet = (float) ($det->iva ?? 0);
            if ($ivaDet < 0.0001 && $g > 0.0001) {
                if ($porc == 15 || abs($porc - 15) < 0.01) {
                    $ivaDet = round($g * 0.15, 2);
                } elseif ($porc == 18 || abs($porc - 18) < 0.01) {
                    $ivaDet = round($g * 0.18, 2);
                } elseif ($porc < 17) {
                    $ivaDet = round($g * 0.15, 2);
                } else {
                    $ivaDet = round($g * ($porc / 100), 2);
                }
            }
            if ($porc == 15 || abs($porc - 15) < 0.01) {
                $iva_15 += $ivaDet;
                $gravada_15 += $g;
            } elseif ($porc == 18 || abs($porc - 18) < 0.01) {
                $iva_18 += $ivaDet;
                $gravada_18 += $g;
            } else {
                if ($porc < 17) {
                    $iva_15 += $ivaDet;
                    $gravada_15 += $g;
                } else {
                    $iva_18 += $ivaDet;
                    $gravada_18 += $g;
                }
            }
        }

        $ivaCabecera = (float) ($venta->iva ?? 0);
        $ivaFilas = $iva_15 + $iva_18;
        if ($ivaCabecera > 0.0001 && $ivaFilas < 0.005) {
            if ($gravada_18 < 0.005 && $gravada_15 > 0) {
                $iva_15 = round($ivaCabecera, 2);
            } elseif ($gravada_15 < 0.005 && $gravada_18 > 0) {
                $iva_18 = round($ivaCabecera, 2);
            } else {
                $iva_15 = round($ivaCabecera, 2);
            }
        } elseif ($ivaCabecera > 0.0001 && abs($ivaCabecera - $ivaFilas) > 0.02) {
            $delta = round($ivaCabecera - $ivaFilas, 2);
            if (abs($delta) < 0.5) {
                if ($gravada_18 < 0.005) {
                    $iva_15 = round($iva_15 + $delta, 2);
                } elseif ($gravada_15 < 0.005) {
                    $iva_18 = round($iva_18 + $delta, 2);
                } else {
                    $iva_15 = round($iva_15 + $delta, 2);
                }
            }
        }

        $descReb = (float) ($venta->descuento ?? 0);
        $subTot = (float) ($venta->sub_total ?? ($gravada_15 + $gravada_18));
        $imptoExento = (float) ($venta->exenta ?? 0);

        // CAI / rango / fecha límite — mismas claves y formato que Accesorios HN
        $cai = data_get($empresa->custom_empresa, 'configuraciones.factura_cai') ?: $documento->resolucion;
        $rangoAuth = data_get($empresa->custom_empresa, 'configuraciones.factura_rango_autorizado') ?: $documento->rangos;
        $fechaLimiteCai = data_get($empresa->custom_empresa, 'configuraciones.factura_fecha_limite');
        if ($fechaLimiteCai) {
            try {
                $fechaLimiteFmt = \Carbon\Carbon::parse($fechaLimiteCai)->format('d/m/Y');
            } catch (\Throwable $e) {
                $fechaLimiteFmt = $fechaLimiteCai;
            }
        } else {
            $fechaLimiteFmt = $documento->fecha
                ? \Carbon\Carbon::parse($documento->fecha)->format('d/m/Y')
                : '';
        }

        $fechaImpresion = \Carbon\Carbon::now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY H:mm');
        $obs = trim((string) ($documento->nota ?? ''));
        if ($obs === '') {
            $obs = trim((string) data_get($empresa->custom_empresa, 'configuraciones.santre_observaciones_ticket', ''));
        }
    @endphp

    <p class="cen b up" style="font-size: 11pt;">{{ strtoupper($empresa->nombre) }}</p>

    <div class="sec">
        <p class="sec-title">Información de contacto</p>
        @if ($direccionCasaMatriz !== '')
            <p class="muted"><span class="b">Dirección de casa matriz:</span> {{ $direccionCasaMatriz }}</p>
        @endif
        @if ($telefonoEmpresa)
            <p class="muted"><span class="b">Teléfono:</span> {{ $telefonoEmpresa }}</p>
        @endif
        @if (!empty($correoEmpresa))
            <p class="muted"><span class="b">Correo electrónico:</span> {{ $correoEmpresa }}</p>
        @endif
        @if ($empresa->nit)
            <p class="muted"><span class="b">RTN:</span> {{ $empresa->nit }}</p>
        @endif
    </div>

    <div class="sec">
        <p class="sec-title">Información de la venta</p>
        <p class="muted"><span class="b">Sucursal:</span> {{ $nombreSucursal }}</p>
        @if ($direccionEstablecimiento !== '')
            <p class="muted"><span class="b">Dirección de establecimiento:</span> {{ $direccionEstablecimiento }}</p>
        @endif
    </div>

    <hr class="d">

    <div class="sec">
        <p><span class="b">Número de factura</span></p>
        <p class="mt1">{{ $numFacturaDisplay }}</p>
        <p class="mt2"><span class="b">Fecha de emisión</span></p>
        <p class="mt1">{{ $fechaEmisionFmt }}</p>
        @if ($metodoPago !== '')
            <p class="mt2"><span class="b">Método de pago</span></p>
            <p class="mt1">{{ $metodoPago }}</p>
        @endif
    </div>

    <div class="sec">
        <p class="sec-title">Información del cliente</p>
        @if ($rtnCliente !== '')
            <p class="muted"><span class="b">RTN:</span> {{ $rtnCliente }}</p>
        @endif
        <p class="muted"><span class="b">Nombre:</span> {{ $codCliente }} - {{ $nombreCliente }}</p>
    </div>

    <hr class="d">

    <table class="lines">
        <thead>
            <tr>
                <th style="width:42%;">Descripción</th>
                <th class="num" style="width:12%;">Cant.</th>
                <th class="num" style="width:23%;">Precio ud.</th>
                <th class="num" style="width:23%;">Total</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($venta->detalles as $detalle)
            @php
                $cant = (float) $detalle->cantidad;
                $gLine = (float) ($detalle->gravada ?? $detalle->sub_total ?? 0);
                $brutoLinea = (float) ($detalle->total ?? 0);
                if ($brutoLinea < 0.0001 && $gLine > 0) {
                    $brutoLinea = $gLine;
                }
                $puMostrar = $cant > 0
                    ? ($gLine > 0 ? ($gLine / $cant) : ((float) $detalle->precio))
                    : (float) $detalle->precio;
            @endphp
            <tr>
                <td class="left">{{ $detalle->nombre_producto }}</td>
                <td class="num">{{ rtrim(rtrim(number_format($cant, 2, '.', ''), '0'), '.') }}</td>
                <td class="num">L {{ number_format($puMostrar, 2) }}</td>
                <td class="num">L {{ number_format($brutoLinea, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <hr class="d">

    <table class="lines" style="border-collapse: collapse;">
        <tr class="tot-row">
            <td>Subtotal</td>
            <td class="val">L {{ number_format($subTot, 2) }}</td>
        </tr>
        <tr class="tot-row">
            <td>Importe exonerado</td>
            <td class="val">L 0.00</td>
        </tr>
        <tr class="tot-row">
            <td>Importe exento</td>
            <td class="val">L {{ number_format($imptoExento, 2) }}</td>
        </tr>
        <tr class="tot-row">
            <td>Importe gravado 15% ISV</td>
            <td class="val">L {{ number_format($gravada_15, 2) }}</td>
        </tr>
        <tr class="tot-row">
            <td>Importe gravado 18% ISV</td>
            <td class="val">L {{ number_format($gravada_18, 2) }}</td>
        </tr>
        <tr class="tot-row">
            <td>Total 15% ISV</td>
            <td class="val">L {{ number_format($iva_15, 2) }}</td>
        </tr>
        <tr class="tot-row">
            <td>Total 18% ISV</td>
            <td class="val">L {{ number_format($iva_18, 2) }}</td>
        </tr>
        <tr class="tot-row">
            <td>Descuentos y rebajas otorgados</td>
            <td class="val">L {{ number_format($descReb, 2) }}</td>
        </tr>
        <tr class="tot-row tot-pay">
            <td class="b">Total</td>
            <td class="val b">L {{ number_format((float) $venta->total, 2) }}</td>
        </tr>
    </table>

    <div class="sec muted">
        <p>Número de orden de compra exenta: {{ $venta->num_orden_exento ?? '' }}</p>
        <p>Número constancia de registro de exonerados:</p>
        <p>Número de registro de SAG:</p>
    </div>

    <div class="fiscal">
        @if ($cai)
            <p class="mt1"><span class="b">CAI</span></p>
            <p class="mt1">{{ $cai }}</p>
        @endif
        <p class="mt2"><span class="b">Total en letras</span></p>
        <p class="mt1 up">{{ strtoupper($dolares) }} LEMPIRAS {{ $centavosNum }}/100</p>
        @if ($rangoAuth)
            <p class="mt2"><span class="b">Rango autorizado</span></p>
            <p class="mt1">{{ $rangoAuth }}</p>
        @endif
        @if ($fechaLimiteFmt)
            <p class="mt2"><span class="b">Fecha limite de emisión:</span></p>
            <p class="mt1">{{ $fechaLimiteFmt }}</p>
        @endif
    </div>

    @if ($obs !== '')
        <div class="sec">
            <p><span class="b">Observaciones:</span> {{ $obs }}</p>
        </div>
    @endif

    <p class="cen b mt2 up" style="font-size: 7.5pt;">LA FACTURA ES BENEFICIO DE TODOS, EXIJALA</p>
    <p class="foot mt2">Original: Cliente — Copia 1: Obligado Tributario Emisor — Copia 2: Archivo</p>
    <p class="foot mt1">Documento Generado por SmartPyme</p>
    <p class="foot mt1">Fecha de impresión: {{ $fechaImpresion }}</p>
</div>
@if (empty($venta->pdf))
<script>window.onload = function () { window.print(); };</script>
@endif
</body>
</html>
