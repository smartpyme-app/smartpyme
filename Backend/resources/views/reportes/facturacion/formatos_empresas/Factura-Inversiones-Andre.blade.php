<!DOCTYPE html>
<html>
<head>
    {{-- <script language="javascript">setTimeout("self.close();",500)</script> --}}
    <title>Inversiones Andre {{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 14px; margin: 0; padding: 0;}
        html, body{
            font-family: serif;
        }

        #factura{
            padding: 30px 50px;
            position: relative;
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
                        <h2>INVERSIONES ANDRE S. DE R. L. DE C. V.</h2>
                        <p style="color: blue;">Soluciones para sus necesidades de sellado de fluidos</p>
                        <br>
                        <h3><b>RTN: 05019013561871</b></h3>
                        <p style="margin: 0px;">Residencial Real del Puente, Calle 3, 1 Avenida, Casa No 9</p>
                        <p style="margin: 0px;">Bloque: K, Municipio: Villanueva, Departamento: Cortés</p>
                        <p style="margin: 0px;">Teléfono: 2670-1407/96324389</p>
                        <p style="margin: 0px;">E:mail: servicioalcliente@inversionesandre.com</p>
                        <p style="margin-top: 5px;"><b>Cliente: </b> {{ $venta->nombre_cliente }}</p>
                        <p><b>Dirección: </b> {{ $venta->id_cliente ? $cliente->direccion : '' }}</p>
                    </td>
                    <td>
                        <h1 style="color: red; font-size: 1.2em;">000-002-1- {{ str_pad($venta->correlativo, 8, '0', STR_PAD_LEFT)}}</h1>
                        <br>
                        <p><b>FECHA:</b> {{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
                        <p><b>ID Cliente:</b> {{ $venta->id_cliente }}</p>
                        <p><b>Cotización:</b></p>
                        <p><b>RTN:</b> {{ $venta->id_cliente ? $cliente->nit : '' }}</p>
                        <p><b>Teléfono:</b> {{ $venta->id_cliente ? $cliente->telefono : '' }}</p>
                    </td>
                </tr>
            </tbody>
        </table>

        <table id="op" style="margin-bottom: 5px;">
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
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td> 
                    <td></td> 
                </tr>
            </tbody>
        </table>
        @php($iva = $venta->empresa()->pluck('iva')->first() / 100)
        
        <table id="productos">
            <thead>
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
                    <td class="cantidad">   {{ number_format($detalle->cantidad, 0) }}</td>
                    <td class="codigo">   {{ $detalle->producto->codigo  }}</td>
                    <td class="producto">   {{ $detalle->nombre_producto  }}</td>
                    <td class="precio">     <span style="float: left;">L </span>{{ number_format($detalle->precio, 2) }}</td>
                    <td class="gravadas">  <span style="float: left;">L </span>{{ number_format($detalle->total, 2) }} </td> 
                </tr>
                @endforeach
                @for ($i = 0; $i < (9 - count($venta->detalles)) ; $i++)
                    <tr>
                        <td class="cantidad"></td>
                        <td class="codigo"></td>
                        <td class="producto"></td>
                        <td class="precio"></td>
                        <td class="gravadas"></td> 
                    </tr>
                @endfor
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3"><span style="font-size: 12px;">Original: Cliente &nbsp;&nbsp;&nbsp; Copia: Emisor</span></td>
                    <td style="padding: 0 3px 0 0; text-align: right;">Importe Exento:</td>
                    <td style="border: 1px solid black;"><span style="float: left;">L </span></td>
                </tr>
                <tr>
                    <td colspan="3">Fecha Límite de Emisión: 04/03/2026</td>
                    <td style="padding: 0 3px 0 0; text-align: right;">Importe Exonerado:</td>
                    <td style="border: 1px solid black;"><span style="float: left;">L </span></td>
                </tr>
                <tr>
                    <td colspan="3">Fecha de Autorización: 04/03/2025</td>
                    <td style="padding: 0 3px 0 0; text-align: right;">Importe Fiscal:</td> 
                    <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($venta->sub_total, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="3"></td>
                    <td style="padding: 0 3px 0 0; text-align: right;">Importe Gravado 15%:</td> 
                    <td style="text-align: right; border: 1px solid black;"><span style="float: left;">L </span>{{ number_format($venta->iva, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="3">RANGO AUTORIZADO: 000-002-01-00002401 / 000-002-01-00002600</td>
                    <td style="padding: 0 3px 0 0; text-align: right;">Importe Gravado 18%:</td>
                    <td style="border: 1px solid black;"><span style="float: left;">L </span></td>
                </tr>
                <tr>
                    <td colspan="3"><b>CAI: 2F86B6-E23258-5C69E0-63BE03-090937-F8</b></td>
                    <td style="padding: 0 3px 0 0; text-align: right;">Desc. y Rebajas:</td>
                    <td style="border: 1px solid black;"><span style="float: left;">L </span></td>
                </tr>
                <tr>
                    <td colspan="3"></td>
                    <td style="padding: 0 3px 0 0; text-align: right;">ISV 15%:</td>
                    <td style="border: 1px solid black;"><span style="float: left;">L </span></td>
                </tr>
                <tr>
                    <td colspan="3"><p style="color: red;">Original: Cliente</p></td>
                    <td style="padding: 0 3px 0 0; text-align: right;">ISV 18%:</td>
                    <td style="border: 1px solid black;"><span style="float: left;">L </span></td>
                </tr>
                <tr>
                    <td colspan="3"> {{$dolares}} CON {{$centavos}}/100 LEMPIRAS. <br> </td>
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

        <div style="position: absolute; bottom: 0px; left: 53px;">
            <span style="float: left; margin-top: 50px;">Firma:</span>
            <img style="height: 90px; margin-left: 20px;" src="{{ asset('img/empresas/firma-inversiones-andre.png') }}" alt="Logo">
        </div>

        <h3 style="text-align: center; margin-top: 30px;">
            ¡Gracias por su Compra! <br>
            La Factura es Beneficio de Todos, "EXIJALA"
        </h3>
    </section>

</body>
</html>
