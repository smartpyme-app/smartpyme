<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  <script language="javascript">setTimeout("self.close();",500)</script>
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
{{-- <body> --}}
<body onload="javascript:print();">
    <div class="text-center">
        <h3>{{ $empresa->nombre }}</h3>
        <p>{{ $empresa->sector }}</p>
        <p>{{ $empresa->propietario }}</p>
        <p>{{ $empresa->direccion }}</p>
        <p><b>Fecha:</b> {{ $venta->created_at->format('d-m-Y h:i:s a') }}</p>
        <p><b>@if($empresa->pais == 'El Salvador')NIT:@else Identificación fiscal:@endif</b> {{ $empresa->nit }}</p> 
        <p><b>@if($empresa->pais == 'El Salvador')NCR:@else Registro tributario:@endif</b> {{ $empresa->ncr }} </p>
        <p><b>GIRO:</b> {{ $empresa->giro }}</p>
        <p><b>CATEGORIA:</b> {{ $empresa->tamano }}<p>
        <p><b>TELÉFONO:</b> {{ $empresa->telefono }}</p>
        {{-- <p><b>CORTE N°:</b> {{ $venta->corte_id }}</p> --}}
        <p><b>ORDEN N°:</b> {{ $venta->id }}</p>
        {{-- <p><b>TICKET:</b> T{{ $venta->correlativo}} &nbsp;&nbsp;&nbsp; CONTADO</p> --}}
        <p><b>CLIENTE:</b> {{ $venta->cliente_nombre ? $venta->cliente_nombre : 'CLIENTE GENERAL'}}</p>
        {{-- <p><b>DUI:</b> {{ $venta->cliente()->first()->dui}}</p> --}}
        <p><b>VENDEDOR:</b> {{ $venta->nombre_usuario }}</p>
        <p><b>MESA: {{ $venta->mesa }}</b></p>
        <p>PRE TICKET</p>
    <hr>
    </div>

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
                <td colspan="3"> {{ $detalle->nombre_producto  }}</td>
            </tr>
            <tr>
                <td> {{ number_format($detalle->cantidad, 2) }}</td>
                <td class="text-center">   ${{ number_format($detalle->precio, 2 ) }}</td>
                <td class="text-right"> ${{ number_format($detalle->total, 2) }} </th>
            </tr>
        @endforeach
        </tbody>
    </table>
    <hr>
    <table style="margin: auto;">
        <tbody>
            <tr>
                <td>Exenta:</td>
                <td><b>${{ number_format($venta->exenta, 2) }}</b></td>
            </tr>
            <tr>
                <td>Gravada:</td>
                <td><b>${{ number_format($venta->total, 2) }}</b></td>
            </tr>
            <tr>
                <td>No sujeta:</td>
                <td><b>${{ number_format($venta->no_sujeta, 2) }}</b></td>
            </tr>
            <tr>
                <td>Propina:</td>
                <td><b>${{ number_format($venta->propina, 2) }}</b></td>
            </tr>
            <tr>
                <td>Descuento:</td>
                <td><b>${{ number_format($venta->descuento, 2) }}</b></td>
            </tr>
        </tbody>
    </table>
    <hr>

    <h2 class="text-center">
        <b>TOTAL: 
        <span style="margin-left: 20px;">${{ number_format($venta->total, 2) }}</span></b>
    </h2>
    <hr>
  {{--   <table style="margin: auto;">
        <tbody>
            <tr>
                <td class="text-center">Efectivo:</td>
                <td>${{ number_format($venta->recibido, 2) }}</td>
            </tr>
            <tr>
                <td class="text-center">Importe:</td>
                <td>${{ number_format($venta->total, 2) }}</td>
            </tr>
            <tr>
                <td class="text-center">Su Cambio:</td>
                <td><b>${{ number_format($venta->recibido - $venta->total, 2) }}</b></td>
            </tr>
        </tbody>
    </table> --}}
    <hr>
    <p class="text-center"> <b>ESTE TICKET NO SUSTITUYE UNA FACTURA<br> </p>
    <p class="text-center"> <b>¡GRACIAS POR SU COMPRA!<br> </p>
    <p class="text-center"> Total de articulos: {{ $venta->detalles->sum('cantidad') }} </p>
    <br><br><br><br>
    <p>.</p>
    <button class="no-print" onClick="window.close();" autofocus>Cerrar</button>


</body>
</html>
