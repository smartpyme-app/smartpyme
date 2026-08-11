<!DOCTYPE html>
<html>
<head>
    <title>{{ $empresa->nombre }} {{ $venta->nombre_documento }} - {{ $venta->correlativo }}</title>
    <style>
        *{ font-size: 14px; margin: 0; padding: 0;}
        html, body{
            font-family: serif;
        }
        @page {
            margin: 2cm 2.5cm;
            margin-top: 2cm;
            margin-bottom: 2cm;
            margin-left: 2.5cm;
            margin-right: 2.5cm;
        }
        #factura{
            padding: 0px 50px;
        }

        p{
            margin: 0px 0px 5px 0px;
        }

        table   {text-align: left; border-collapse: collapse; width: 100%; }
        table th, table td{height: 0.4cm; text-align: left; padding: 4px;}

        table tbody {
            border: 1px solid black;
        }

        #productos th{
            border: 1px solid black;
        }

        #productos tbody td {
            border-left: 1px solid black;
            border-right: 1px solid black;
        }

        #op td {
            border: 1px solid black;
            height: 0.3cm;
            text-align: center;
        }

        #op th{
            font-size: 11px;
            background-color: #252598;
            color: white;
            text-align: center;
            border: 1px solid black;
            vertical-align: top;
        }

        .cantidad{ width: 1cm; text-align: center;}
        .codigo{ width: 2cm; text-align: center;}
        .precio{ width: 3.3cm; text-align: right;}
        .gravadas{ width: 2.2cm; text-align: right;}

        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }

        #productos {
            page-break-inside: auto;
            page-break-before: auto;
            page-break-after: auto;
            margin-top: 10px;
            margin-bottom: 10px;
            border-spacing: 0;
        }

        #productos thead {
            display: table-row-group;
            page-break-after: avoid;
            page-break-inside: avoid;
        }

        #productos tfoot {
            display: table-row-group;
            page-break-inside: avoid;
            page-break-before: avoid;
            page-break-after: avoid;
        }

        #productos tbody {
            page-break-inside: auto;
        }

        #productos tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        #productos td, #productos th {
            padding: 5px;
        }

        #productos thead tr:first-child th {
            padding-top: 15px;
        }

        #productos tbody tr:first-child td {
            padding-top: 10px;
        }
    </style>
</head>
<body>
<section id="factura">
@php
    $corr = str_pad((string) $venta->correlativo, 8, '0', STR_PAD_LEFT);
    $prefDoc = trim((string) ($documento->prefijo ?? ''));
    $numFacturaDisplay = $prefDoc !== '' ? $prefDoc . ' ' . $corr : $corr;

    $cai = data_get($empresa->custom_empresa, 'configuraciones.factura_cai') ?: ($documento->resolucion ?? null);
    $rangoAuth = data_get($empresa->custom_empresa, 'configuraciones.factura_rango_autorizado') ?: ($documento->rangos ?? null);
    $fechaLimiteCai = data_get($empresa->custom_empresa, 'configuraciones.factura_fecha_limite');
    $fechaAutorizacionCai = data_get($empresa->custom_empresa, 'configuraciones.factura_fecha_autorizacion');
    $sloganEmpresa = trim((string) data_get($empresa->custom_empresa, 'configuraciones.factura_slogan', ''));

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

    if ($fechaAutorizacionCai) {
        try {
            $fechaAutorizacionFmt = \Carbon\Carbon::parse($fechaAutorizacionCai)->format('d/m/Y');
        } catch (\Throwable $e) {
            $fechaAutorizacionFmt = $fechaAutorizacionCai;
        }
    } else {
        $fechaAutorizacionFmt = '';
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

    $direccionCliente = '';
    $telefonoCliente = '';
    $rtnCliente = '';
    $codigoCliente = '';
    if ($venta->id_cliente && isset($cliente) && $cliente) {
        if (method_exists($cliente, 'getDireccionEfectiva')) {
            $direccionCliente = trim((string) ($cliente->getDireccionEfectiva() ?? ''));
        }
        if ($direccionCliente === '') {
            $direccionCliente = trim((string) ($cliente->direccion ?? $cliente->empresa_direccion ?? ''));
        }
        if (method_exists($cliente, 'getTelefonoEfectivo')) {
            $telefonoCliente = trim((string) ($cliente->getTelefonoEfectivo() ?? ''));
        }
        if ($telefonoCliente === '') {
            $telefonoCliente = trim((string) ($cliente->telefono ?? $cliente->empresa_telefono ?? ''));
        }
        $rtnCliente = trim((string) ($cliente->nit ?? ''));
        if ($rtnCliente === '') {
            $rtnCliente = trim((string) ($cliente->dui ?? ''));
        }
        $codigoCliente = $cliente->codigo_cliente ?? '';
    }

    $ivaEmpresa = (float) ($venta->empresa()->pluck('iva')->first() ?? 15);
    $iva_15 = 0.0;
    $iva_18 = 0.0;
    $gravada_15 = 0.0;
    $gravada_18 = 0.0;
    $importe_exento = 0.0;
    $descReb = (float) ($venta->descuento ?? 0);

    foreach ($venta->detalles as $det) {
        $porc = $det->porcentaje_impuesto !== null && $det->porcentaje_impuesto !== ''
            ? (float) $det->porcentaje_impuesto
            : $ivaEmpresa;
        $tipoGrav = strtolower(trim((string) ($det->tipo_gravado ?? 'gravada')));

        if (abs($porc) < 0.01 || $tipoGrav === 'exenta' || $tipoGrav === 'exento') {
            $montoLineaExento = (float) ($det->exenta ?? 0);
            if ($montoLineaExento <= 0) {
                $montoLineaExento = (float) ($det->gravada ?? $det->sub_total ?? $det->total ?? 0);
            }
            $importe_exento += $montoLineaExento;
            continue;
        }

        $g = (float) ($det->gravada ?? $det->sub_total ?? 0);
        $ivaDet = (float) ($det->iva ?? 0);

        if ($porc == 15 || abs($porc - 15) < 0.01 || $porc < 17) {
            $iva_15 += $ivaDet;
            $gravada_15 += $g;
        } else {
            $iva_18 += $ivaDet;
            $gravada_18 += $g;
        }
    }

    $logoEmpresa = $venta->empresa()->pluck('logo')->first();
@endphp

    <table id="header">
        <tbody style="border: 0px;">
            <tr>
                <td>
                    @if ($logoEmpresa)
                        <img height="100" src="{{ asset('img/'.$logoEmpresa) }}" alt="Logo">
                    @endif
                </td>
                <td><h1 style="text-align: right; font-size: 1.4em;">FACTURA</h1></td>
            </tr>
            <tr>
                <td>
                    <h2>{{ strtoupper($empresa->nombre) }}</h2>
                    @if ($sloganEmpresa !== '')
                        <p style="color: blue;">{{ $sloganEmpresa }}</p>
                    @endif
                    <br>
                    @if ($empresa->nit)
                        <h3><b>RTN: {{ $empresa->nit }}</b></h3>
                    @endif
                    @if ($direccionFactura)
                        <p style="margin: 0px;">{{ $direccionFactura }}</p>
                    @endif
                    @if ($telefonoFactura)
                        <p style="margin: 0px;">Teléfono: {{ $telefonoFactura }}</p>
                    @endif
                    @if ($correoFactura)
                        <p style="margin: 0px;">E-mail: {{ $correoFactura }}</p>
                    @endif
                    <p style="margin-top: 5px;"><b>Cliente: </b> {{ $venta->nombre_cliente }}</p>
                    <p><b>Dirección: </b> {{ $direccionCliente }}</p>
                </td>
                <td>
                    <h1 style="color: red; font-size: 1.2em;">{{ $numFacturaDisplay }}</h1>
                    <br>
                    <p><b>FECHA:</b> {{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
                    <p><b>ID Cliente:</b> {{ $codigoCliente }}</p>
                    <p><b>Cotización:</b> {{ $venta->num_cotizacion }}</p>
                    <p><b>RTN:</b> {{ $rtnCliente }}</p>
                    <p><b>Teléfono:</b> {{ $telefonoCliente }}</p>
                </td>
            </tr>
        </tbody>
    </table>

    <table id="op">
        <thead>
            <tr>
                <th>NUMERO DE OP</th>
                <th>NUMERO CORRELATIVO ORDEN DE COMPRA EXENTO</th>
                <th>NUMERO DE REGISTRO DE EXONERADO</th>
                <th>NUMERO DE REGISTRO AGRICULTURA Y GANADERIA</th>
                <th>VENCIMIENTO DE FACTURA</th>
                <th>TERMINOS DE PAGO</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $venta->num_orden }}</td>
                <td>{{ $venta->num_orden_exento }}</td>
                <td>{{ ($venta->id_cliente && isset($cliente) && $cliente) ? ($cliente->ncr ?? '') : '' }}</td>
                <td></td>
                <td>{{ $venta->fecha_pago ? \Carbon\Carbon::parse($venta->fecha_pago)->format('d/m/Y') : '' }}</td>
                <td>{{ $venta->condicion }}</td>
            </tr>
        </tbody>
    </table>
    <br>

    <table id="productos">
        <thead style="display: table-row-group;">
            <tr>
                <th>CANT</th>
                <th style="text-align: center;">ITEM #</th>
                <th>DESCRIPCION</th>
                <th>PRECIO UNIT.</th>
                <th>IMPORTE</th>
            </tr>
        </thead>
        <tbody>
            @foreach($venta->detalles as $detalle)
            <tr>
                <td class="cantidad">{{ number_format($detalle->cantidad, 0) }}</td>
                <td class="codigo">{{ optional($detalle->producto)->codigo }}</td>
                <td class="producto">{{ $detalle->nombre_producto }}</td>
                <td class="precio"><span style="float: left;">L </span>{{ number_format($detalle->precio, 2) }}</td>
                <td class="gravadas"><span style="float: left;">L </span>{{ number_format($detalle->total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot style="display: table-row-group; page-break-inside: avoid;">
            <tr>
                <td colspan="3"><span style="font-size: 12px;">Original: Cliente &nbsp;&nbsp;&nbsp; Copia: Emisor</span></td>
                <td style="padding: 0 3px 0 0; text-align: right;">Importe Exento:</td>
                <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($importe_exento, 2) }}</td>
            </tr>
            <tr>
                <td colspan="3">
                    @if ($fechaLimiteFmt !== '')
                        Fecha Límite de Emisión: {{ $fechaLimiteFmt }}
                    @endif
                </td>
                <td style="padding: 0 3px 0 0; text-align: right;">Importe Exonerado:</td>
                <td style="border: 1px solid black;"><span style="float: left;">L </span>0.00</td>
            </tr>
            <tr>
                <td colspan="3">
                    @if ($fechaAutorizacionFmt !== '')
                        Fecha de Autorización: {{ $fechaAutorizacionFmt }}
                    @endif
                </td>
                <td style="padding: 0 3px 0 0; text-align: right;">Importe Fiscal:</td>
                <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($venta->sub_total, 2) }}</td>
            </tr>
            <tr>
                <td colspan="3"></td>
                <td style="padding: 0 3px 0 0; text-align: right;">Importe Gravado 15%:</td>
                <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($gravada_15, 2) }}</td>
            </tr>
            <tr>
                <td colspan="3">
                    @if ($rangoAuth)
                        RANGO {{ $rangoAuth }}
                    @endif
                </td>
                <td style="padding: 0 3px 0 0; text-align: right;">Importe Gravado 18%:</td>
                <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($gravada_18, 2) }}</td>
            </tr>
            <tr>
                <td colspan="3">
                    @if ($cai)
                        <b>CAI: {{ $cai }}</b>
                    @endif
                </td>
                <td style="padding: 0 3px 0 0; text-align: right;">Desc. y Rebajas:</td>
                <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($descReb, 2) }}</td>
            </tr>
            <tr>
                <td colspan="3"></td>
                <td style="padding: 0 3px 0 0; text-align: right;">ISV 15%:</td>
                <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($iva_15, 2) }}</td>
            </tr>
            <tr>
                <td colspan="3"><p style="color: red;">Original: Cliente</p></td>
                <td style="padding: 0 3px 0 0; text-align: right;">ISV 18%:</td>
                <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($iva_18, 2) }}</td>
            </tr>
            <tr>
                <td colspan="3">{{ $dolares }} CON {{ $centavos }}/100 LEMPIRAS.<br></td>
                <td style="padding: 0 3px 0 0; text-align: right;">TOTAL A PAGAR:</td>
                <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($venta->total, 2) }}</td>
            </tr>
            <tr>
                <td colspan="3" style="border-top: 1px solid black;">Escribir en Letras el Total a Pagar</td>
                <td style="padding: 0 3px 0 0; text-align: right;">TOTAL A PAGAR:</td>
                <td style="border: 1px solid black; text-align: left;"><span style="float: left;">$ </span></td>
            </tr>
        </tfoot>
    </table>

    <h3 style="text-align: center; margin-top: 30px;">
        ¡Gracias por su Compra! <br>
        La Factura es Beneficio de Todos, "EXIJALA"
    </h3>
</section>
</body>
</html>
