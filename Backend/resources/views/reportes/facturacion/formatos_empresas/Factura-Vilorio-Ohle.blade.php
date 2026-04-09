<!DOCTYPE html>
<html>
<head>
    {{-- <script language="javascript">setTimeout("self.close();",500)</script> --}}
    <title>Vilorio Ohle {{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 14px; margin: 0; padding: 0;}
        html, body{
            font-family: serif;
        }
        #factura{
            padding: 50px 50px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th, table td {
            border: 1px solid black;
            text-align: left;
            padding: 2px 5px;
        }

    </style>

</head>
<body>  

    <section id="factura">
        <div style="margin-bottom: 10px;">
            <h2>VILORIO OHLE S. DE R. L.</h2>
            <p>{{ $empresa->direccion }}</p>
            <p>
                @if($empresa->municipio){{ $empresa->municipio }}@endif
                @if($empresa->municipio && $empresa->departamento)@endif
                @if($empresa->departamento), {{ $empresa->departamento }}@endif
            </p>
            <p>RTN: {{ $empresa->ncr }}</p>
            <p>Email: {{ $empresa->correo }}</p>
            <p>Teléfono: {{ $empresa->telefono }}</p>
        </div>
        <h1 style="text-align: center; font-size: 1em; margin-bottom: 10px;">FACTURA</h1>
        <table>
            <tbody>
                <tr>
                    <td>
                        <p><b>Nombre del Cliente:</b></p>
                        <p>{{ $venta->nombre_cliente }}</p>
                    </td>
                    <td>
                        <p style="text-align: right;">CAI: 476A90-2900B3-C2D8E0-63BE03-0909D8-A4</p>
                        <p style="text-align: right;"><b>Nùmero de Factura:</b> {{ $venta->correlativo }}</p>
                        <p style="text-align: right;"><b>FECHA:</b> {{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
                        <p style="text-align: right;"><b>Página:</b> 1</p>
                    </td>
                </tr>
            </tbody>
        </table>

        <table>
            <thead>
                <tr>
                    <th>Código de cliente</th>
                    <th>RTN</th>
                    <th>Vendedor</th>
                    <th>Términos</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $venta->id_cliente ? $cliente->codigo_cliente : '' }}</td>
                    <td>{{ $venta->id_cliente ? $cliente->nit : '' }}</td>
                    <td>{{ $venta->nombre_vendedor }}</td>
                    <td>{{ $venta->condicion }}</td> 
                </tr>
            </tbody>
        </table>

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
                    <th>Código Producto</th>
                    <th>Descripción Producto</th>
                    <th style="text-align: right;">Cantidad</th>
                    <th style="text-align: right;">Precio Unitario</th>
                    <th style="text-align: right;">Descuento</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody style="min-height: 100px;">
                @foreach($venta->detalles as $detalle)
                <tr>
                    <td>{{ $detalle->producto->codigo }}</td>
                    <td>{{ $detalle->nombre_producto }}</td>
                    <td style="text-align: right;">{{ number_format($detalle->cantidad, 0) }}</td>
                    <td style="text-align: right;">{{ number_format($detalle->precio, 2) }}</td>
                    <td style="text-align: right;">{{ number_format($detalle->descuento, 2) }}</td>
                    <td style="text-align: right;">{{ number_format($detalle->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot style="display: table-row-group; page-break-inside: avoid;">
                <tr>
                    <td style="border-bottom: none;" colspan="4">No. correlativo orden de compra exento: {{ $venta->num_orden_exento }}</td>
                    <td style="padding: 0 3px 0 0; text-align: right;">Importe Exonerado:</td>
                    <td style="border: 1px solid black;"><span style="float: left;">L </span></td>
                </tr>
                <tr>
                    <td style="border-bottom: none; border-top: none;" colspan="4">No. correlativo de constancia de registro de exonerado:</td>
                    <td style="padding: 0 3px 0 0; text-align: right;">Importe Exento:</td>
                    <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($importe_exento, 2) }}</td>
                </tr>
                <tr>
                    <td style="border-bottom: none; border-top: none;" colspan="4">No. de registro de la SAG:</td>
                    <td style="padding: 0 3px 0 0; text-align: right;">Importe Gravado 15%:</td> 
                    <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($gravada_15, 2) }}</td>
                </tr>
                <tr>
                    <td style="border-bottom: none; border-top: none;" colspan="4"></td>
                    <td style="padding: 0 3px 0 0; text-align: right;">Importe Gravado 18%:</td>
                    <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($gravada_18, 2) }}</td>
                </tr>
                <tr>
                    <td style="border-bottom: none; border-top: none;" colspan="4"></td>
                    <td style="padding: 0 3px 0 0; text-align: right;">ISV 15%:</td>
                    <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($iva_15, 2) }}</td>
                </tr>
                <tr>
                    <td style="border-bottom: none; border-top: none;" colspan="4"></td>
                    <td style="padding: 0 3px 0 0; text-align: right;">ISV 18%:</td>
                    <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($iva_18, 2) }}</td>
                </tr>
                <tr>
                    <td style="border-bottom: none; border-top: none;" colspan="4">_____________________________   &nbsp;&nbsp; &nbsp;&nbsp; &nbsp;&nbsp;    ______________________________</td>
                    <td style="padding: 0 3px 0 0; text-align: right;">Desc. y Rebajas:</td>
                    <td style="border: 1px solid black;"><span style="float: left;">L </span></td>
                </tr>
                <tr>
                    <td style="border-top: none;" colspan="4">Firma VIOH    
                        &nbsp;&nbsp; &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        &nbsp;&nbsp; &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        &nbsp;&nbsp; &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                         Firma de recibido de conformidad</td>
                    <td style="padding: 0 3px 0 0; text-align: right;">TOTAL A PAGAR:</td>
                    <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($venta->total, 2) }}</td>
                </tr>
            </tfoot>
        </table>
        <br>
        <p> <b>CONDICIONES:</b> </p>
            <p>Debo y pagare esta factua en su vencimiento, en caso de ixurnp& pagare tteres rrwatorb del 4% mensual.</p>
            <p>La factwa se considera cancelada con recho de la empresa o a efecto con deposno o transferencia a la cuenta de la empresa.</p>
            <p>Todo Chewe dewero por cualqüer motvo se pagara L800.OO gastos administrativos</p>
            <br>
            <p>Rango Autorizado de Factura: 000-00301-0001701 a 000-003-01-00022000 Fecha Limite Emision: 13/12/2025</p>
    </section>

</body>
</html>
