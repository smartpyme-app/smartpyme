<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  <!-- <script language="javascript">setTimeout("self.close();",500)</script> -->
  <title>Factura</title>
  <style>
    h1, h2, h3{
        margin: 3pt;
    }
    html, body {
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI",
    "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Fira Sans",
    "Droid Sans", "Helvetica Neue", sans-serif;
        margin: 0pt;
        padding: 0pt;
        font-size: 9pt;
    }
    hr { border: none; height: 2px; /* Set the hr color */ color: #000; /* old IE */ background-color: #333; /* Modern Browsers */ }

    p{ margin: 0px; };
    table{width: 100%; margin: auto; text-align: left; border-collapse: collapse;}
    table td{height: 12pt;}
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .no-print{position: absolute;}
  </style>
  
  <style media="print"> .no-print{display: none; } </style>

</head>
{{-- <body onload="javascript:print();"> --}}
<body>
  
    <h3 class="text-center">Gasolinera Suchitoto</h3>
    <p class="text-center">TIENDA DE CONVENIENCIA</p>
    <h2 class="text-center">LA ESTANCIA</h2>
    <p>{{ $venta->empresa->nombre_propietario }}</p>
    <p>{{ $venta->empresa->direccion }}</p>
    <p><b>Fecha:</b> {{ $venta->created_at->format('d-m-Y h:i:s a') }}</p>
    <p><b>NIT:</b> {{ $venta->empresa->nit }}</p> 
    <p><b>NCR:</b> {{ $venta->empresa->nrc }} </p>
    <p><b>GIRO:</b> {{ $venta->empresa->actividad_economica }}</p>
    <p><b>CORTE N°:</b> {{ $venta->corte_id }}</p>
    <p><b>VENTA N°:</b> {{ $venta->id }}</p>
    <p><b>TICKET:</b> T{{ $venta->correlativo}} &nbsp;&nbsp;&nbsp; $venta->condicion</p>
    <p><b>CLIENTE:</b> {{ $venta->cliente_nombre ? $venta->cliente_nombre : 'CLIENTE GENERAL'}}</p>
    <p><b>DUI:</b> {{ $venta->cliente()->pluck('dui')->first()}}</p>
    <p><b>VENDEDOR:</b> {{ $venta->usuario }}</p>

    <p><b>CÓDIGO DE GENERACIÓN:</b> <br> {{ $DTE['identificacion']['codigoGeneracion'] }}</p>
    <p><b>NÚMERO DE CONTROL:</b> <br> {{ $DTE['identificacion']['numeroControl'] }}</p>
    <p><b>SELLO DE RECEPCIÓN:</b> <br> {{ $DTE['sello'] }}</p>

    <p class="text-center">
        {!! '<img id="qrcode" width="150" height="150" src="data:image/png;base64,' . DNS2D::getBarcodePNG($venta->qr, 'QRCODE', 10, 10, array(0,0,0), true) . '" alt="barcode"   />' !!}
    </p>
    
    <hr>

    <table style="margin: auto;">
        <thead>
            <tr>
                {{-- <th>Productos</th> --}}
                <th>Cantidad</th>
                <th class="text-center">Precio</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
        @foreach($venta->detalles as $detalle)
            <tr>
                <td colspan="3"> {{ $detalle->producto_nombre  }}</td>
            </tr>
            <tr>
                <td> {{ rtrim(sprintf('%.8F', $detalle->cantidad), '0') }}</td>
                <td class="text-center">   $ {{ number_format($detalle->precio, 2 ) }}</td>
                <td class="text-right"> $ {{ number_format($detalle->total, 2) }} </th>
            </tr>
        @endforeach
        </tbody>
    </table>
    <hr>
    <table style="margin: auto;">
        <tbody>
            <tr>
                <td class="text-center">Descuento: <b>$ {{ number_format($venta->descuento, 2) }}</b></td>
            </tr>
            <tr>
                <td class="text-center">Exenta: <b>$ {{ number_format($venta->exenta, 2) }}</b></td>
            </tr>
            <tr>
                <td class="text-center">No sujeta: <b>$ {{ number_format($venta->no_sujeta, 2) }}</b></td>
            </tr>
        </tbody>
    </table>
    <hr>

    <h2 class="text-center">
        <b>TOTAL: 
        <span style="margin-left: 20px;">$ {{ number_format($venta->total, 2) }}</span></b>
    </h2>
    <hr>
    <table style="margin: auto;">
        <tbody>
            <tr>
                <td class="text-center">Efectivo: $ {{ number_format($venta->recibido, 2) }}</td>
            </tr>
            <tr>
                <td class="text-center">Importe: $ {{ number_format($venta->total, 2) }}</td>
            </tr>
            <tr>
                <td class="text-center">Su Cambio: <b>$ {{ number_format($venta->recibido - $venta->total, 2) }}</b></td>
            </tr>
        </tbody>
    </table>
    <hr>
    
    <p class="text-center"> <b>¡GRACIAS POR SU COMPRA!<br> </p>
    <p class="text-center"> Total de articulos: {{ $venta->detalles->sum('cantidad') }} </p>
    <br><br><br><br>
    <p>.</p>
    <button class="no-print" onClick="window.close();" autofocus>Cerrar</button>


</body>
</html>
