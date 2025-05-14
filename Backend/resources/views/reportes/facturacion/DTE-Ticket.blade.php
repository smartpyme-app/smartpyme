<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  <script language="javascript">setTimeout("self.close();",2000)</script>
  <title>Ticket</title>
  <style media="all">
    h1, h2, h3{
        margin: 3pt;
    }
    .header, .footer{
        text-align: center;
    }
    .header img{
        height: 100px;
    }
    html, body {
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI",
    "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Fira Sans",
    "Droid Sans", "Helvetica Neue", sans-serif;
        margin: 0pt;
        padding: 0pt;
        font-size: 9pt;
    }

    p{ margin: 0px; }
    table td{height: 12pt;}
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-left { text-align: left; }
  </style>
  
  <style media="print"> .no-print{display: none; } </style>

</head>
<body onload="javascript:print();">
  
    <div class="header">
        <p class="no-print">
            <button onClick="window.print();" autofocus>Imprimir</button>
            <button onClick="window.close();" autofocus>Cerrar</button>
            <br><br>
        </p>
        <br>
        {{--        @if ($venta->empresa->logo)--}}
        {{--            <img src="{{ asset('img/'.$venta->empresa->logo) }}" alt="Logo">--}}
        {{--        @endif--}}
        @if ($venta->sucursal()->first())
            <h3>{{ $venta->sucursal()->pluck('nombre')->first() }}</h3>
        @else
            <h3>{{ $venta->empresa->nombre }}</h3>
        @endif
        <p>{{ $venta->empresa->sector }}</p>
        <p>{{ $venta->empresa->nombre_propietario }}</p>

        @if ($venta->sucursal()->first())
            <p>{{ $venta->sucursal()->first()->direccion }}</p>
        @else
            <p>{{ $venta->empresa->direccion }}</p>
        @endif
        @if($venta->empresa->ncr)
            <p><b>NCR:</b> {{ $venta->empresa->ncr }} </p>
        @endif
        @if($venta->empresa->nit)
            <p><b>NIT:</b> {{ $venta->empresa->nit }}</p>
        @endif
        @if($venta->empresa->giro)
            <p><b>GIRO:</b> {{ $venta->empresa->giro }}</p>
        @endif

        @if ($venta->sucursal()->first()->telefono)
            <p><b>TELÉFONO:</b> {{ $venta->sucursal()->first()->telefono }}</p>
        @elseif($venta->empresa->telefono)
            <p><b>TELÉFONO:</b> {{ $venta->empresa->telefono }}</p>
        @endif


        <p>
            <b>FECHA Y HORA:</b> <br>
            {{ \Carbon\Carbon::parse($venta->created_at)->format('d/m/Y') }} | {{ \Carbon\Carbon::parse($venta->created_at)->format('h:i:s a') }}
        </p>
        <p><b>TICKET:</b># {{ $venta->correlativo }}</p>
        <p><b>MÉTODO DE PAGO:</b> {{ $venta->forma_pago}}</p>
        <p><b>CAJERO:</b> {{ $venta->nombre_usuario }}</p>

        @if ($venta->cliente())
            <p><b>Cliente:</b></p>
            <p>NOMBRE: {{ $venta->nombre_cliente }}</p> 
            @if ($venta->cliente()->pluck('telefono')->first())
                <p>TELÉFONO: {{ $venta->cliente()->pluck('telefono')->first() }}</p> 
            @endif
            @if ($venta->cliente()->pluck('direccion')->first())
                <p>DIRECCIÓN: {{ $venta->cliente()->pluck('direccion')->first() }}</p> 
            @endif
        @endif

        <br>
        <h3 class="text-center">{{ $venta->nombre_documento }}</h3>
        <br>

        <p><b>CÓDIGO DE GENERACIÓN:</b> <br> {{ $DTE['identificacion']['codigoGeneracion'] }}</p>
        <p><b>NÚMERO DE CONTROL:</b> <br> {{ $DTE['identificacion']['numeroControl'] }}</p>
        <p><b>SELLO DE RECEPCIÓN:</b> <br> {{ $DTE['sello'] }}</p>
    </div>

    <br>
    <p class="text-center">
        {!! '<img id="qrcode" width="150" height="150" src="data:image/png;base64,' . DNS2D::getBarcodePNG($venta->qr, 'QRCODE', 10, 10, array(0,0,0), true) . '" alt="barcode"   />' !!}
    </p>
    
    <hr>

    <table style="width: 100%; margin: auto;">
        <thead>
            <tr>
                <th class="text-left">DETALLE</th>
                <th width="50px" class="text-center">
                    @if ($venta->empresa->modulo_paquetes)
                        LB/FT
                    @else
                        CANT
                    @endif
                </th>
                <th width="50px" class="text-center">P.U.</th>
                <th width="50px" class="text-right">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @php($iva = 13 / 100);
            
            @if ($venta->descripcion_personalizada)
                <tr>
                    <td>
                        {{ $venta->descripcion_impresion }}
                    </td>
                    <td class="text-center">1</td>
                    <td class="text-center">${{ number_format($venta->sub_total + $venta->iva, 2) }}</td>
                    <td class="text-right">${{ number_format($venta->sub_total + $venta->iva, 2) }}G</td>
                </tr>
            @else
                @foreach($venta->detalles as $detalle)
                <tr>
                    <td>
                        {{ $detalle->nombre_producto }}
                        @if ($detalle->producto()->first()->promocion()->first())
                          @foreach ($detalle->producto()->first()->promocion()->first()->detalles()->get() as $det)
                            <p style="font-size: 8px !important; margin: 0px;">{{ $det->nombre_producto }} x {{ $det->cantidad }}</p>
                          @endforeach
                        @endif
                    </td>
                    <td class="text-center">{{ $detalle->cantidad }}</td>
                    <td class="text-center">${{ number_format($detalle->precio + (($venta->iva != 0) ? ($detalle->precio * $iva) : 0), 2) }}</td>
                    <td class="text-right">${{ number_format($detalle->total + (($venta->iva != 0)  ? ($detalle->total * $iva) : 0), 2) }}G</td>
                </tr>
                @endforeach
            @endif
        </tbody>
        <tfoot>
            @if (isset($DTE['resumen']['tributos']))
            <tr>
                <td class="text-right">SubTotal:</td>
                <td class="text-right"><b>$ {{ number_format($DTE['resumen']['subTotalVentas'], 2) }}</b></td>
            </tr>
            @endif
            <tr class="mt-4">
                <td class="text-right" colspan="3">VENTA GRAVADA:</td>
                <td class="text-right">${{number_format($venta->sub_total + $venta->iva,2) }}</td>
            </tr>
            <tr>
                <td class="text-right" colspan="3">VENTA EXENTA:</td>
                <td class="text-right">${{number_format($venta->exenta,2) }}</td>
            </tr>
            <tr>
                <td class="text-right" colspan="3">VENTA NO SUJETA:</td>
                <td class="text-right">${{number_format($venta->no_sujeta,2) }}</td>
            </tr>
            @if (isset($DTE['resumen']['tributos']))
                @foreach ($DTE['resumen']['tributos'] as $tributo)
                <tr>
                    <td class="text-right">{{ $tributo['descripcion'] }}:</td>
                    <td class="text-right"><b>${{ number_format($tributo['valor'], 2) }}</b></td>
                </tr>
                @endforeach
            @endif

            @if ($venta->cuenta_a_terceros > 0)
                <tr>
                    <td class="text-right" colspan="3">CUENTA A TERCEROS:</td>
                    <td class="text-right">${{number_format($venta->cuenta_a_terceros,2) }}</td>
                </tr>
            @endif
            @if ($venta->costo_envio)
                <tr>
                    <td class="text-right" colspan="3">ENVIO:</td>
                    <td class="text-right">${{number_format($venta->costo_envio,2)}}</td>
                </tr>
            @endif
            <tr>
                <td class="text-right" colspan="3"><b>TOTAL</b>:</td>
                <td class="text-right"><b>${{number_format($venta->total + $venta->costo_envio,2)}}</b></td>
            </tr>
        </tfoot>
    </table>
    <br>
    <hr style="margin: 5px;">
    
    @if ($venta->empresa->modulo_paquetes)
        <p class="text-center">
            TOTAL DE PIEZAS: {{ $venta->detalles->count() }}</td>
        </p>
    @endif

    <table style="margin: auto;">
        <tr>
            <td>Método de pago:</td><td class="text-right">{{ $venta->forma_pago }}</td>
        </tr>
        <tr>
            <td>Recibido:</td><td class="text-right">${{ number_format($venta->monto_pago,2)}}</td>
        </tr>
        <tr>
            <td>Cambio:</td><td class="text-right">${{ number_format($venta->cambio,2)}}</td>
        </tr>
    </table>


    <p class="text-center"><small>G = GRAVADO &nbsp;&nbsp; E = EXENTO &nbsp;&nbsp; N = NO SUJETO</small></p>

    @if($venta->total > 200)
    <br>
    {{-- <p>LLENAR SI LA VENTA ES MAYOR/IGUAL A $200.00</p> --}}
    <table>
        <tbody>
            <tr>
                <td width="100px">NOMBRE:</td>
                <td>________________________</td>
            </tr>
            <tr>
                <td width="100px">NIT:</td>
                <td>________________________</td>
            </tr>
            <tr>
                <td width="100px">DUI:</td>
                <td>________________________</td>
            </tr>
            <tr>
                <td width="100px">FIRMA:</td>
                <td>________________________</td>
            </tr>
        </tbody>
    </table>
    @endif
    <br>

    <br>
    <p style="color: #fff;">.</p>


</body>
</html>
