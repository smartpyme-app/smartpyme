<!DOCTYPE html>
<html>
<head>
    {{-- <script language="javascript">setTimeout("self.close();",500)</script> --}}
    <title>{{ $empresa->nombre }} {{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
    <style>

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            font-family: serif;
            font-size: 11px;
            line-height: 1.2;
        }
        @page {
            /* Márgenes uniformes 2 cm (área imprimible respecto al borde de la hoja) */
            margin: 2cm;
            size: letter;
        }
        #factura {
            padding: 6px 18px 0 18px;
        }

        p {
            margin: 0 0 3px 0;
        }

        #header td { padding: 2px 4px; vertical-align: top; }
        #header h1 { margin: 0 0 2px 0; line-height: 1.15; }
        #header h2 { font-size: 12px; margin: 0 0 3px 0; line-height: 1.15; }
        #header h3 { font-size: 11px; margin: 0 0 2px 0; }
        #header .logo-factura { height: 68px; width: auto; display: block; }

        table { text-align: left; border-collapse: collapse; width: 100%; }
        table th, table td { text-align: left; padding: 2px 3px; vertical-align: top; }

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
            font-size: 9px;
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

        #suma       {top: 19.9cm; left: 16.5cm; width: 2cm; text-align: right;}
        #iva        {top: 22.1cm; left: 16.5cm; width: 2cm; text-align: right;}
        #total      {top: 23.2cm; left: 16.5cm; width: 2cm; text-align: right;}

        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }

        #productos {
            margin-top: 4px;
            margin-bottom: 2px;
            border-spacing: 0;
            border-collapse: collapse;
            page-break-inside: auto;
            page-break-before: auto;
            page-break-after: auto;
        }

        #productos thead {
            display: table-row-group;
            page-break-after: avoid;
            page-break-inside: avoid;
        }

        #productos tfoot {
            display: table-row-group;
            page-break-before: avoid;
        }

        /* Evitar que el encabezado se repita cuando solo el pie de tabla pasa a otra página */
        #productos tbody {
            page-break-inside: auto;
        }

        /* Forzar que el pie de tabla se mantenga con el cuerpo de la tabla */
        #productos tfoot {
            page-break-inside: avoid;
            page-break-before: avoid;
            page-break-after: avoid;
        }

        #productos tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        #productos td, #productos th {
            padding: 3px 4px;
            font-size: 10px;
        }

        #productos thead tr:first-child th {
            padding-top: 4px;
            padding-bottom: 3px;
        }

        #productos tbody tr:first-child td {
            padding-top: 3px;
        }

        #productos tfoot td {
            padding: 2px 4px;
            font-size: 10px;
            line-height: 1.15;
        }

        /* Forzar espaciado en la primera fila de datos cuando la tabla se divide */
        #productos tbody tr:first-child {
            page-break-before: auto;
            page-break-after: auto;
        }

        /* Tabla de firmas sin bordes */
        #firmas, #firmas tbody, #firmas tr, #firmas td, #firmas th {
            border: none !important;
        }
        #firmas { font-size: 10px; margin-top: 14px !important; }
        #firmas .firma-logo { height: 58px; width: auto; }

        .pie-cai { font-size: 10px; text-align: center; margin-top: 6px; line-height: 1.3; }
        .pie-cai p { margin: 2px 0; }
        .agradece-factura { font-size: 11px; text-align: center; margin: 10px 0 0 0; line-height: 1.25; font-weight: normal; }

    </style>

</head>
<body>

    <section id="factura">
        @php
            $corr = str_pad((string) $venta->correlativo, 8, '0', STR_PAD_LEFT);
            $prefPorSucursalJson = data_get($empresa->custom_empresa, 'configuraciones.prefijo_factura_lilian_ohle_por_sucursal', []);
            $prefPorSucursal = is_array($prefPorSucursalJson) ? $prefPorSucursalJson : [];
            $idSucVenta = $venta->id_sucursal;
            $prefFijoSucursal = null;
            if ($idSucVenta !== null) {
                $prefFijoSucursal = $prefPorSucursal[(string) $idSucVenta] ?? $prefPorSucursal[$idSucVenta] ?? null;
            }
            if ($prefFijoSucursal !== null && trim((string) $prefFijoSucursal) !== '') {
                $numFacturaDisplay = trim((string) $prefFijoSucursal).' '.$corr;
            } else {
                $prefDoc = trim((string) ($documento->prefijo ?? ''));
                $numFacturaDisplay = $prefDoc !== '' ? $prefDoc.' '.$corr : '000-003-01- '.$corr;
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
            $cuentaCondiciones = trim((string) data_get($empresa->custom_empresa, 'configuraciones.factura_condiciones_cuenta_banco', ''));
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
        <table id="header">
            <tbody style="border: 0px;">
                <tr>
                    <td>
                        @if ($venta->empresa()->pluck('logo')->first())
                            <img class="logo-factura" src="{{ asset('img/'.$venta->empresa()->pluck('logo')->first()) }}" alt="Logo">
                        @endif
                    </td>
                    <td><h1 style="text-align: right; font-size: 13px; margin: 0;">FACTURA</h1></td>
                </tr>
                <tr>
                    <td>
                        <h2>{{ strtoupper($empresa->nombre) }}</h2>
                        @if($direccionFactura || $telefonoFactura || $correoFactura)
                        @if($empresa->nit)<h3><b>RTN: {{ $empresa->nit }}</b></h3>@endif
                        @if($direccionFactura)<p style="margin: 0px;">{{ $direccionFactura }}</p>@endif
                        @if($telefonoFactura)<p style="margin: 0px;">Teléfono: {{ $telefonoFactura }}</p>@endif
                        @if($correoFactura)<p style="margin: 0px;">E-mail: {{ $correoFactura }}</p>@endif
                        @endif
                        <p style="margin-top: 3px;"><b>Cliente: </b> {{ $venta->nombre_cliente }}</p>
                        <p><b>Dirección: </b> {{ $venta->id_cliente ? $cliente->direccion : '' }}</p>
                    </td>
                    <td>
                        <h1 style="color: red; font-size: 12px; margin: 0 0 4px 0;">{{ $numFacturaDisplay }}</h1>
                        <p style="margin-top: 0;"><b>FECHA:</b> {{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
                        <p><b>ID Cliente:</b> {{ $venta->cliente ? $venta->cliente->codigo_cliente : '' }}</p>
                        <p><b>Cotización:</b> {{ $venta->num_cotizacion }}</p>
                        <p><b>RTN:</b> {{ $venta->id_cliente ? $cliente->nit : '' }}</p>
                        <p><b>Teléfono:</b> {{ $venta->id_cliente ? $cliente->telefono : '' }}</p>
                    </td>
                </tr>
            </tbody>
        </table>
<!-- 
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
                    <td>{{ $venta->id_cliente ? $cliente->ncr : '' }}</td>
                    <td></td>
                    <td>{{ \Carbon\Carbon::parse($venta->fecha_pago)->format('d/m/Y') }}</td> 
                    <td>{{ $venta->condicion }}</td> 
                </tr>
            </tbody>
        </table> -->
        <!-- <br> -->
        <?php
            $iva = $venta->empresa()->pluck('iva')->first() / 100;
            $ivaEmpresa = (float) ($venta->empresa()->pluck('iva')->first() ?? 18);
            $iva_15 = 0;
            $iva_18 = 0;
            $gravada_15 = 0;
            $gravada_18 = 0;
            $importe_exento = 0;
            foreach ($venta->detalles as $det) {
                $porc = $det->porcentaje_impuesto !== null && $det->porcentaje_impuesto !== '' ? (float) $det->porcentaje_impuesto : $ivaEmpresa;
                $tipoGrav = $det->tipo_gravado ?? 'gravada';
                // 0% o línea exenta: no debe ir a "Importe Gravado 15%" (antes caía en porc < 17)
                if (abs($porc) < 0.01 || $tipoGrav === 'exenta') {
                    $montoLineaExento = (float) ($det->exenta ?? 0);
                    if ($montoLineaExento <= 0) {
                        $montoLineaExento = (float) ($det->gravada ?? $det->sub_total ?? $det->total ?? 0);
                    }
                    $importe_exento += $montoLineaExento;
                    continue;
                }
                if ($porc == 15 || (abs($porc - 15) < 0.01)) {
                    $iva_15 += (float) ($det->iva ?? 0);
                    $gravada_15 += (float) ($det->gravada ?? $det->sub_total ?? 0);
                } elseif ($porc == 18 || (abs($porc - 18) < 0.01)) {
                    $iva_18 += (float) ($det->iva ?? 0);
                    $gravada_18 += (float) ($det->gravada ?? $det->sub_total ?? 0);
                } else {
                    if ($porc < 17) {
                        $iva_15 += (float) ($det->iva ?? 0);
                        $gravada_15 += (float) ($det->gravada ?? $det->sub_total ?? 0);
                    } else {
                        $iva_18 += (float) ($det->iva ?? 0);
                        $gravada_18 += (float) ($det->gravada ?? $det->sub_total ?? 0);
                    }
                }
            }
        ?>
        
        <table id="productos">
            <thead style="display: table-row-group;">
                <tr>
                    <th>CANT</th>
                    <th style="text-align: center;">CÓD. BARRAS</th>
                    <th>DESCRIPCION</th>
                    <th>PRECIO UNIT.</th>
                    <th style="text-align: center;">% IMPUESTO</th>
                    <th style="text-align: center;">% DESCUENTO</th>
                    <th>IMPORTE</th>
                </tr>
            </thead>
            <tbody>
                @foreach($venta->detalles as $detalle)
                <?php $producto = $detalle->producto ?? null; ?>
                <tr>
                    <td class="cantidad">   {{ number_format($detalle->cantidad, 0) }}</td>
                    <td class="codigo" style="text-align: center;">{{ $producto ? ($producto->barcode ?: $producto->codigo) : '-' }}</td>
                    <td class="producto">   {{ $detalle->nombre_producto  }}</td>
                    <td class="precio">     <span style="float: left;">L </span>{{ number_format($detalle->precio, 2) }}</td>
                    <td class="codigo" style="text-align: center;">{{ number_format($detalle->porcentaje_impuesto ?? $ivaEmpresa ?? 0, 0) }}%</td>
                    <td class="codigo" style="text-align: center;">{{ number_format($detalle->porcentaje_descuento ?? 0, 0) }}%</td>
                    <td class="gravadas">  <span style="float: left;">L </span>{{ number_format($detalle->total, 2) }} </td> 
                </tr>
                @endforeach
            </tbody>
            <tfoot style="display: table-row-group; page-break-inside: avoid;">
                <tr>
                    <td colspan="5"><span style="font-size: 8px;">Original: Cliente &nbsp;&nbsp; Copia: Emisor &nbsp;&nbsp; 2da Copia: Contabilidad &nbsp;&nbsp; 3ra Copia: Expediente Cliente</span></td>
                    <td style="padding: 0 3px 0 0; text-align: right;">Importe Exento:</td>
                    <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($importe_exento, 2) }}</td>
                </tr>
                <tr>
                    {{-- Fecha Límite de Emisión (comentado de momento) --}}
                    <td colspan="5"></td>
                    <td style="padding: 0 3px 0 0; text-align: right;">Importe Exonerado:</td>
                    <td style="border: 1px solid black;"><span style="float: left;">L </span></td>
                </tr>
                <tr>
                    {{-- Fecha de Autorización (comentado de momento) --}}
                    <td colspan="5"></td>
                    <td style="padding: 0 3px 0 0; text-align: right;">Importe Fiscal:</td> 
                    <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($venta->sub_total, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="5"></td>
                    <td style="padding: 0 3px 0 0; text-align: right;">Importe Gravado 15%:</td> 
                    <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($gravada_15, 2) }}</td>
                </tr>
                <tr>
                    {{-- Rango autorizado (comentado de momento) --}}
                    <td colspan="5"></td>
                    <td style="padding: 0 3px 0 0; text-align: right;">Importe Gravado 18%:</td>
                    <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($gravada_18, 2) }}</td>
                </tr>
                <tr>
                    {{-- CAI (comentado de momento) --}}
                    <td colspan="5"></td>
                    <td style="padding: 0 3px 0 0; text-align: right;">Desc. y Rebajas:</td>
                    <td style="border: 1px solid black;"><span style="float: left;">L </span></td>
                </tr>
                <tr>
                    <td colspan="5"></td>
                    <td style="padding: 0 3px 0 0; text-align: right;">ISV 15%:</td>
                    <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($iva_15, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="5"><p style="color: red; margin: 0; font-size: 9px;">Original: Cliente</p></td>
                    <td style="padding: 0 3px 0 0; text-align: right;">ISV 18%:</td>
                    <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($iva_18, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="5">{{$dolares}} CON {{$centavos}}/100 LEMPIRAS.</td>
                    <td style="padding: 0 3px 0 0; text-align: right;">TOTAL A PAGAR:</td>
                    <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($venta->total, 2) }}</td>
                </tr>
            </tfoot>
        </table>

        <table id="firmas" style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 50%; vertical-align: bottom; padding-right: 12px; border: none;">
                    @if ($venta->empresa()->pluck('logo')->first())
                    <img class="firma-logo" src="{{ asset('img/'.$venta->empresa()->pluck('logo')->first()) }}" alt="Firma">
                    @endif
                    <span style="display: block; margin-bottom: 2px;">Firma:</span>
                </td>
                <td style="width: 50%; vertical-align: bottom; text-align: right; border: none;">
                    ____________________________<br>
                    Firma del cliente
                </td>
            </tr>
        </table>

        @if (!empty($documento->nota))
            <div style="font-size: 10px; text-align: justify; margin-top: 8px; line-height: 1.25;">
                {!! nl2br(e($documento->nota)) !!}
            </div>
        @endif
        <div class="pie-cai">
            @if ($cai)
                <p><strong>CAI:</strong> {{ $cai }}</p>
            @endif
            @if ($rangoAuth)
                <p><strong>RANGO AUTORIZADO:</strong> {{ $rangoAuth }}</p>
            @endif
            @if ($fechaLimiteFmt)
                <p><strong>FECHA LÍMITE DE EMISIÓN:</strong> {{ $fechaLimiteFmt }}</p>
            @endif
        </div>

        <h3 class="agradece-factura">
        ¡Gracias por su Compra! <br>
        La Factura es Beneficio de Todos, "EXIJALA"
        </h3>
    </section>

</body>
</html>
