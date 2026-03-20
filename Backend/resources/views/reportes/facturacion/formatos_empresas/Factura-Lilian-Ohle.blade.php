<!DOCTYPE html>
<html>
<head>
    {{-- <script language="javascript">setTimeout("self.close();",500)</script> --}}
    <title>{{ $empresa->nombre }} {{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
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

        #suma       {top: 19.9cm; left: 16.5cm; width: 2cm; text-align: right;}
        #iva        {top: 22.1cm; left: 16.5cm; width: 2cm; text-align: right;}
        #total      {top: 23.2cm; left: 16.5cm; width: 2cm; text-align: right;}

        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }

        #productos {
            page-break-inside: auto;
            page-break-before: auto;
            page-break-after: auto;
        }

        #productos thead {
            display: table-row-group;
            page-break-after: avoid;
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
            padding: 5px;
        }

        /* Asegurar que las páginas tengan margen superior cuando la tabla se divide */
        @page {
            margin-top: 2cm;
            margin-bottom: 2cm;
            margin-left: 2.5cm;
            margin-right: 2.5cm;
        }

        /* Espaciado adicional para la tabla cuando se divide */
        #productos {
            margin-top: 10px;
            margin-bottom: 10px;
        }

        /* Forzar espaciado en páginas que contienen tablas divididas */
        @page :first {
            margin-top: 2cm;
        }

        @page :left {
            margin-top: 2cm;
        }

        @page :right {
            margin-top: 2cm;
        }

        /* Asegurar que el contenido de la tabla tenga espaciado */
        #productos thead tr:first-child th {
            padding-top: 15px;
        }

        /* Espaciado adicional para cuando la tabla continúa en nueva página */
        #productos tbody tr:first-child td {
            padding-top: 10px;
        }

        /* Forzar espaciado en la primera fila de datos cuando la tabla se divide */
        #productos tbody tr:first-child {
            page-break-before: auto;
            page-break-after: auto;
        }

        /* Asegurar que el encabezado de la tabla tenga espaciado cuando se repite */
        #productos thead {
            page-break-after: avoid;
        }

        /* Controlar mejor la repetición del encabezado */
        #productos {
            page-break-inside: auto;
            page-break-before: auto;
            page-break-after: auto;
        }

        /* Asegurar que el pie de tabla no cause repetición del encabezado */
        #productos tfoot {
            page-break-inside: avoid;
            page-break-before: avoid;
        }

        /* Regla específica para evitar que el encabezado se repita cuando solo el pie de tabla cambia de página */
        #productos {
            page-break-inside: auto;
        }

        /* Asegurar que el encabezado nunca se repita */
        #productos thead {
            display: table-row-group;
            page-break-after: avoid;
            page-break-inside: avoid;
        }

        /* Espaciado adicional para el contenido de la tabla */
        #productos {
            border-spacing: 0;
            border-collapse: collapse;
        }

    </style>

</head>
<body>
<body>  

    <section id="factura">
        <table id="header">
            <tbody style="border: 0px;">
                <tr>
                    <td>
                        @if ($venta->empresa()->pluck('logo')->first())
                            <img height="100" src="{{ asset('img/'.$venta->empresa()->pluck('logo')->first()) }}" alt="Logo">
                        @endif
                    </td>
                    <td><h1 style="text-align: right; font-size: 1.4em;">FACTURA</h1></td>
                </tr>
                <tr>
                    <td>
                        <h2>{{ strtoupper($empresa->nombre) }}</h2>
                        @if($empresa->direccion || $empresa->telefono || $empresa->email)
                        <br>
                        @if($empresa->nit)<h3><b>RTN: {{ $empresa->nit }}</b></h3>@endif
                        @if($empresa->direccion)<p style="margin: 0px;">{{ $empresa->direccion }}</p>@endif
                        @if($empresa->telefono)<p style="margin: 0px;">Teléfono: {{ $empresa->telefono }}</p>@endif
                        @if($empresa->email)<p style="margin: 0px;">E-mail: {{ $empresa->email }}</p>@endif
                        @endif
                        <p style="margin-top: 5px;"><b>Cliente: </b> {{ $venta->nombre_cliente }}</p>
                        <p><b>Dirección: </b> {{ $venta->id_cliente ? $cliente->direccion : '' }}</p>
                    </td>
                    <td>
                        <h1 style="color: red; font-size: 1.2em;">000-002-01- {{ str_pad($venta->correlativo, 8, '0', STR_PAD_LEFT)}}</h1>
                        <br>
                        <p><b>FECHA:</b> {{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
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
            foreach ($venta->detalles as $det) {
                $porc = $det->porcentaje_impuesto !== null && $det->porcentaje_impuesto !== '' ? (float) $det->porcentaje_impuesto : $ivaEmpresa;
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
                    <td colspan="5"><span style="font-size: 12px;">Original: Cliente &nbsp;&nbsp;&nbsp; Copia: Emisor</span></td>
                    <td style="padding: 0 3px 0 0; text-align: right;">Importe Exento:</td>
                    <td style="border: 1px solid black;"><span style="float: left;">L </span></td>
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
                    <td colspan="5"><p style="color: red;">Original: Cliente</p></td>
                    <td style="padding: 0 3px 0 0; text-align: right;">ISV 18%:</td>
                    <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($iva_18, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="5"> {{$dolares}} CON {{$centavos}}/100 LEMPIRAS. <br> </td>
                    <td style="padding: 0 3px 0 0; text-align: right;">TOTAL A PAGAR:</td>
                    <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($venta->total, 2) }}</td>
                </tr>
            </tfoot>
        </table>

        <table style="margin-top: 50px; width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 50%; vertical-align: top; padding-right: 20px;">
                    <span style="display: block; margin-bottom: 5px;">Firma:</span>
                    @if ($venta->empresa()->pluck('logo')->first())
                        <img style="height: 90px;" src="{{ asset('img/'.$venta->empresa()->pluck('logo')->first()) }}" alt="Firma">
                    @endif
                </td>
                <td style="width: 50%; vertical-align: top; text-align: right;">
                    <span style="display: block; margin-bottom: 5px;">Firma del cliente:</span>
                    <div style="height: 90px; border-bottom: 1px solid black; min-width: 200px; margin-left: auto;"></div>
                </td>
            </tr>
        </table>

        <h3 style="text-align: center; margin-top: 30px;">
            ¡Gracias por su Compra! <br>
            La Factura es Beneficio de Todos, "EXIJALA"
        </h3>
    </section>

</body>
</html>
