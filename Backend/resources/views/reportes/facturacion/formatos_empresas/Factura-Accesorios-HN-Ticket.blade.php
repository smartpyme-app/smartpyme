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
            font-family: "DejaVu Sans Mono", "Courier New", Courier, monospace;
            font-size: 8pt;
            line-height: 1.25;
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
        .mt2 { margin-top: 3pt; }
        .mt1 { margin-top: 2pt; }
        .logo { max-height: 72px; max-width: 100%; }
        hr.d { border: none; border-top: 1px dashed #000; margin: 4pt 0; }
        hr.s { border: none; border-top: 1px solid #000; margin: 4pt 0; }
        table.meta { width: 100%; border-collapse: collapse; }
        table.meta td { vertical-align: top; padding: 0 0 2pt; font-size: 7.5pt; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 4pt; }
        table.lines th {
            font-size: 7pt;
            text-align: left;
            border-bottom: 1px solid #000;
            padding: 2pt 0;
            font-weight: bold;
        }
        table.lines th:last-child { text-align: right; }
        .line-main td { padding: 3pt 0 0; vertical-align: top; font-size: 7.5pt; }
        .line-sub { font-size: 7pt; padding-left: 2mm !important; color: #111; }
        .tot-row td { padding: 1pt 0; font-size: 7.5pt; }
        .tot-row .val { text-align: right; white-space: nowrap; }
        .tot-pay td { padding-top: 4pt; font-size: 8.5pt; }
        .legal {
            font-size: 6.5pt;
            text-align: justify;
            margin-top: 6pt;
            line-height: 1.2;
        }
        .fiscal { font-size: 7pt; margin-top: 5pt; text-align: center; }
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

    <div class="cen">
        @php
            $logoRaw = ($venta->empresa && $venta->empresa->logo) ? $venta->empresa->logo : ($empresa->logo ?? null);
            $logoRel = null;
            if ($logoRaw) {
                $logoRel = ltrim(str_replace('\\', '/', (string) $logoRaw), '/');
                if ($logoRel === '' || strpos($logoRel, '..') !== false) {
                    $logoRel = null;
                }
            }
            $logoSrc = null;
            if ($logoRel) {
                $fullLogo = public_path('img'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $logoRel));
                if (is_file($fullLogo) && is_readable($fullLogo)) {
                    if (!empty($venta->pdf)) {
                        $ext = strtolower(pathinfo($fullLogo, PATHINFO_EXTENSION));
                        if ($ext === 'png') {
                            $mime = 'image/png';
                        } elseif ($ext === 'gif') {
                            $mime = 'image/gif';
                        } elseif ($ext === 'webp') {
                            $mime = 'image/webp';
                        } else {
                            $mime = 'image/jpeg';
                        }
                        $logoSrc = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($fullLogo));
                    } else {
                        $logoSrc = asset('img/'.$logoRel);
                    }
                }
            }
        @endphp
        @if ($logoSrc)
            <img class="logo" src="{{ $logoSrc }}" alt="">
        @endif
        <p class="b up mt1" style="font-size: 11pt;">{{ strtoupper($empresa->nombre) }}</p>
        @php
            $slogan = data_get($empresa->custom_empresa, 'configuraciones.accesorios_slogan')
                ?: ($empresa->giro ?: $empresa->sector);
        @endphp
        @if ($slogan)
            <p class="up mt1">{{ strtoupper($slogan) }}</p>
        @endif
        @if ($empresa->nit)
            <p class="mt1"><span class="b">RTN:</span> {{ $empresa->nit }}</p>
        @endif
        @php
            $sucursalVenta = $venta->sucursal ?? $venta->sucursal()->first();
            $telefonoFactura = ($sucursalVenta && trim((string) ($sucursalVenta->telefono ?? '')) !== '')
                ? $sucursalVenta->telefono
                : ($empresa->telefono ?? null);
            $direccionFactura = ($sucursalVenta && trim((string) ($sucursalVenta->direccion ?? '')) !== '')
                ? $sucursalVenta->direccion
                : ($empresa->direccion ?? null);
            $correoFactura = ($sucursalVenta && trim((string) ($sucursalVenta->correo ?? '')) !== '')
                ? $sucursalVenta->correo
                : ($empresa->correo ?? null);
        @endphp
        @if ($telefonoFactura)
            <p class="mt1"><span class="b">TEL.</span> {{ $telefonoFactura }}</p>
        @endif
        @if ($direccionFactura)
            <p class="mt1 up" style="font-size: 7pt;">{{ $direccionFactura }}</p>
        @endif
        @if (!empty($correoFactura))
            <p class="mt1" style="font-size: 7pt;">{{ strtoupper($correoFactura) }}</p>
        @endif
        @php
            $redes = data_get($empresa->custom_empresa, 'configuraciones.accesorios_redes_sociales');
        @endphp
        @if ($redes)
            <p class="mt1 b up">{{ $redes }}</p>
        @endif
    </div>

    <hr class="s">

    @php
        $pref = trim((string) ($documento->prefijo ?? ''));
        $corr = str_pad((string) $venta->correlativo, 8, '0', STR_PAD_LEFT);
        $numFacturaDisplay = $pref !== '' ? $pref . $corr : $corr;
        $codCliente = $venta->id_cliente && $cliente && $cliente->codigo_cliente !== null && $cliente->codigo_cliente !== ''
            ? $cliente->codigo_cliente
            : '0';
        $nombreClienteFactura = strtoupper($venta->nombre_cliente ?? 'CONSUMIDOR FINAL');
        $terminos = strtoupper(trim((string) ($venta->forma_pago ?: $venta->condicion ?: '')));
        $estadoFactura = strtoupper(trim((string) ($venta->estado ?? 'PAGADA')));
        $fechaFactura = \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y');
    @endphp

    <table class="meta">
        <tr>
            <td class="left" style="width:58%;">
                <p><span class="b">{{ $etiquetaNumero }}:</span> {{ $numFacturaDisplay }}</p>
                <p class="mt1"><span class="b">CLIENTE:</span> {{ $codCliente }} - {{ $nombreClienteFactura }}</p>
                @if ($terminos !== '')
                    <p class="mt1"><span class="b">TERMINOS:</span> {{ $terminos }}</p>
                @endif
                <p class="mt1"><span class="b">ESTADO:</span> {{ $estadoFactura }}</p>
            </td>
            <td class="right">
                <p><span class="b">FECHA:</span> {{ $fechaFactura }}</p>
            </td>
        </tr>
    </table>

    <hr class="d">

    @php
        $ivaEmpresa = (float) ($venta->empresa()->pluck('iva')->first() ?? 18);
        $iva_15 = 0.0;
        $iva_18 = 0.0;
        $gravada_15 = 0.0;
        $gravada_18 = 0.0;

        foreach ($venta->detalles as $det) {
            $porc = $det->porcentaje_impuesto !== null && $det->porcentaje_impuesto !== '' ? (float) $det->porcentaje_impuesto : $ivaEmpresa;
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
    @endphp

    <table class="lines">
        <thead>
            <tr>
                <th>CANT / PRECIO / DESCRIPCIÓN</th>
                <th style="width:22mm;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($venta->detalles as $detalle)
            @php
                $cod = optional($detalle->producto)->codigo;
                $cant = (float) $detalle->cantidad;
                $gLine = (float) ($detalle->gravada ?? $detalle->sub_total ?? 0);
                $ivaLine = (float) ($detalle->iva ?? 0);
                $porcLine = $detalle->porcentaje_impuesto !== null && $detalle->porcentaje_impuesto !== ''
                    ? (float) $detalle->porcentaje_impuesto
                    : $ivaEmpresa;
                if ($ivaLine < 0.0001 && $gLine > 0.0001) {
                    if ($porcLine == 15 || abs($porcLine - 15) < 0.01) {
                        $ivaLine = round($gLine * 0.15, 2);
                    } elseif ($porcLine == 18 || abs($porcLine - 18) < 0.01) {
                        $ivaLine = round($gLine * 0.18, 2);
                    } elseif ($porcLine < 17) {
                        $ivaLine = round($gLine * 0.15, 2);
                    } else {
                        $ivaLine = round($gLine * ($porcLine / 100), 2);
                    }
                }
                $brutoLinea = (float) ($detalle->total ?? 0);
                if ($brutoLinea < 0.0001 && $gLine > 0) {
                    $brutoLinea = $gLine;
                }
                $puMostrar = $cant > 0 ? ($brutoLinea / $cant) : (float) $detalle->precio;
            @endphp
            <tr class="line-main">
                <td class="left">
                    {{ number_format($cant, 0) }} X {{ number_format($puMostrar, 0) }} {{ strtoupper($detalle->nombre_producto) }}
                </td>
                <td class="right">{{ number_format($brutoLinea, 2) }}</td>
            </tr>
            @if ($ivaLine > 0 || $cod)
            <tr>
                <td class="line-sub left" colspan="2">
                    @if ($ivaLine > 0)
                        @php
                            $pImp = $detalle->porcentaje_impuesto !== null && $detalle->porcentaje_impuesto !== ''
                                ? (float) $detalle->porcentaje_impuesto
                                : ($iva_15 > 0 && $iva_18 <= 0 ? 15 : ($iva_18 > 0 ? 18 : $ivaEmpresa));
                        @endphp
                        ISV ({{ number_format($pImp, 0) }}%): {{ number_format($ivaLine, 2) }}
                    @endif
                    @if ($cod)
                        @if ($ivaLine > 0)&nbsp;&nbsp;@endif
                        COD: {{ $cod }}
                    @endif
                </td>
            </tr>
            @endif
        @endforeach
        </tbody>
    </table>

    <hr class="d">

    @php
        $descReb = (float) ($venta->descuento ?? 0);
        $subTot = (float) ($venta->sub_total ?? 0);
        $imptoExento = (float) ($venta->exenta ?? 0);
        $saldo = (float) ($venta->saldo ?? 0);
        $abono = max(0, (float) $venta->total - $saldo);
        $fp = strtoupper((string) ($venta->forma_pago ?? ''));
        $efectivo = (strpos($fp, 'EFECT') !== false || strpos($fp, 'CASH') !== false)
            ? (float) ($venta->monto_pago ?? 0)
            : 0.0;
        $cambio = (float) ($venta->cambio ?? 0);
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
    @endphp

    <table class="lines" style="border-collapse: collapse;">
        <tr class="tot-row">
            <td class="lbl">DESCUENTOS Y REBAJAS:</td>
            <td class="val">L{{ number_format($descReb, 2) }}</td>
        </tr>
        <tr class="tot-row">
            <td class="lbl">SUBTOTAL:</td>
            <td class="val">L{{ number_format($subTot, 2) }}</td>
        </tr>
        <tr class="tot-row">
            <td class="lbl">IMPORTE EXENTO:</td>
            <td class="val">L{{ number_format($imptoExento, 2) }}</td>
        </tr>
        <tr class="tot-row">
            <td class="lbl">IMPORTE EXONERADO:</td>
            <td class="val">L0.00</td>
        </tr>
        <tr class="tot-row">
            <td class="lbl">IMPORTE GRAVADO 15%:</td>
            <td class="val">L{{ number_format($gravada_15, 2) }}</td>
        </tr>
        <tr class="tot-row">
            <td class="lbl">IMPORTE GRAVADO 18%:</td>
            <td class="val">L{{ number_format($gravada_18, 2) }}</td>
        </tr>
        <tr class="tot-row">
            <td class="lbl">ISV (15%):</td>
            <td class="val">L{{ number_format($iva_15, 2) }}</td>
        </tr>
        <tr class="tot-row">
            <td class="lbl">ISV (18%):</td>
            <td class="val">L{{ number_format($iva_18, 2) }}</td>
        </tr>
        <tr class="tot-row tot-pay">
            <td class="lbl b">TOTAL A PAGAR:</td>
            <td class="val b">L{{ number_format((float) $venta->total, 2) }}</td>
        </tr>
    </table>

    <p class="mt2 cen b up" style="font-size: 7.5pt;">
        {{ strtoupper($dolares) }} CON {{ $centavosNum }}/100
    </p>

    <table class="lines mt2">
        <tr class="tot-row">
            <td class="lbl">ABONO:</td>
            <td class="val">L{{ number_format($abono, 2) }}</td>
        </tr>
        <tr class="tot-row">
            <td class="lbl">SALDO:</td>
            <td class="val">L{{ number_format($saldo, 2) }}</td>
        </tr>
        <tr class="tot-row">
            <td class="lbl">EFECTIVO:</td>
            <td class="val">L{{ number_format($efectivo, 2) }}</td>
        </tr>
        <tr class="tot-row">
            <td class="lbl">CAMBIO:</td>
            <td class="val">L{{ number_format($cambio, 2) }}</td>
        </tr>
    </table>

    <hr class="d">

    <p style="font-size: 7pt;" class="mt1">N° ORDEN DE COMPRA EXENTA: {{ $venta->num_orden_exento ?? '' }}</p>
    <p style="font-size: 7pt;">N° CONST. REGISTRO EXONERADO: </p>
    <p style="font-size: 7pt;">N° REGISTRO SAG: </p>

    <p class="cen b mt2" style="font-size: 7.5pt;">ORIGINAL: CLIENTE / COPIA: EMISOR</p>

    @if ($documento->nota)
        <div class="legal up mt2">{!! nl2br(e($documento->nota)) !!}</div>
    @else
        <div class="legal up mt2">@include('reportes.facturacion.formatos_empresas._accesorios-hn-texto-legal-default')</div>
    @endif

    <div class="fiscal up">
        @if ($cai)
            <p class="mt1"><span class="b">CAI:</span> {{ $cai }}</p>
        @endif
        @if ($rangoAuth)
            <p class="mt1"><span class="b">RANGO AUTORIZADO:</span> {{ $rangoAuth }}</p>
        @endif
        @if ($fechaLimiteFmt)
            <p class="mt1"><span class="b">FECHA LIMITE:</span> {{ $fechaLimiteFmt }}</p>
        @endif
    </div>

    <p class="cen mt2 b" style="font-size: 8pt;">LA FACTURA ES A BENEFICIO DE TODOS, &quot;EXIJALA&quot;</p>
</div>
@if (empty($venta->pdf))
<script>window.onload = function () { window.print(); };</script>
@endif
</body>
</html>
