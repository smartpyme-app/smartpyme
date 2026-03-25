<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  {{-- <script language="javascript">setTimeout("self.close();",500)</script> --}}
  <title>Ticket Devolución</title>
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
<body>
{{-- <body onload="javascript:print();"> --}}
    @php $simbolo_moneda = optional($empresa->currency)->currency_symbol ?? '$'; @endphp
    <p class="no-print">
        <button onClick="window.print();" autofocus>Imprimir</button>
        <button onClick="window.close();" autofocus>Cerrar</button>
        <br><br>
    </p>
    <br>
    
    <div class="text-center">
        <h2>Devolución de venta</h2>
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
        <p><b>CORTE N°:</b> {{ $venta->corte_id }}</p>
        <p><b>VENTA N°:</b> {{ $venta->id }}</p>
        <p><b>TICKET:</b> T{{ $venta->correlativo}} &nbsp;&nbsp;&nbsp; CONTADO</p>
        <p><b>CLIENTE:</b> {{ $venta->nombre_cliente}}</p>
        {{-- <p><b>DUI:</b> {{ $venta->cliente()->first()->dui}}</p> --}}
        <p><b>VENDEDOR:</b> {{ $venta->nombre_usuario }}</p>
        <p>
            @if ($venta->metodo_pago == "Credito")
                <span>Crédito</span>
            @else
                <span>Contado</span>
            @endif
        </p>
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
                <td class="text-center">   {{ $simbolo_moneda }}{{ number_format($detalle->precio, 2 ) }}</td>
                <td class="text-right"> {{ $simbolo_moneda }}{{ number_format($detalle->total, 2) }}G </th>
            </tr>
        @endforeach
        </tbody>
    </table>
    <hr>
    <table style="margin: auto;">
        <tbody>
            <tr>
                <td>Exenta:</td>
                <td><b>{{ $simbolo_moneda }}{{ number_format($venta->exenta, 2) }}</b></td>
            </tr>
            <tr>
                <td>Gravada:</td>
                <td><b>{{ $simbolo_moneda }}{{ number_format($venta->gravada, 2) }}</b></td>
            </tr>
            <tr>
                <td>No sujeta:</td>
                <td><b>{{ $simbolo_moneda }}{{ number_format($venta->no_sujeta, 2) }}</b></td>
            </tr>
            <tr>
                <td>Propina:</td>
                <td><b>{{ $simbolo_moneda }}{{ number_format($venta->propina, 2) }}</b></td>
            </tr>
            <tr>
                <td>Descuento:</td>
                <td><b>{{ $simbolo_moneda }}{{ number_format($venta->descuento, 2) }}</b></td>
            </tr>
        </tbody>
    </table>
    <hr>

    <h2 class="text-center">
        <b>TOTAL: 
        <span style="margin-left: 20px;">{{ $simbolo_moneda }}{{ number_format($venta->total, 2) }}</span></b>
    </h2>
    <hr>
    <table style="margin: auto;">
        <tbody>
            <tr>
                <td class="text-center">Efectivo:</td>
                <td>{{ $simbolo_moneda }}{{ number_format($venta->recibido, 2) }}</td>
            </tr>
            <tr>
                <td class="text-center">Importe:</td>
                <td>{{ $simbolo_moneda }}{{ number_format($venta->total, 2) }}</td>
            </tr>
            <tr>
                <td class="text-center">Su Cambio:</td>
                <td><b>{{ $simbolo_moneda }}{{ number_format($venta->recibido - $venta->total, 2) }}</b></td>
            </tr>
        </tbody>
    </table>
    <hr>

    <p class="text-center"><small>G = GRAVADO &nbsp;&nbsp; E = EXENTO &nbsp;&nbsp; N = NO SUJETO</small></p>

    @if($venta->total_venta > 200)
    <br>
    {{-- <p>LLENAR SI LA VENTA ES MAYOR/IGUAL A $200.00</p> --}}
    <table>
        <tbody>
            <tr>
                <td width="100px">NOMBRE:</td>
                <td>________________________</td>
            </tr>
            <tr>
                <td width="100px">@if($empresa->pais == 'El Salvador')NIT:@else Identificación fiscal:@endif</td>
                <td>________________________</td>
            </tr>
            <tr>
                <td width="100px">@if($empresa->pais == 'El Salvador')DUI:@else Número de identificación:@endif</td>
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
    <p class="text-center"> <b>ESTE TICKET NO SUSTITUYE UNA FACTURA<br> </p>
    <p class="text-center"> <b>¡GRACIAS POR SU COMPRA!<br> </p>
    

{{--     <div class="footer">
        @if ($documento->rangos)
            <p>SERIE AUTORIZADA: <br> {{ $documento->rangos }}</p>
        @endif
        @if ($documento->resolucion)
            <p>RESOLUCIÓN: <br> {{ $documento->resolucion }}</p>
        @endif
        @if ($documento->fecha)
            <p>DE FECHA: <br>
                <?php
                 
                $meses = array("Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");
                 
                echo \Carbon\Carbon::parse($documento->fecha)->format('d')." de ".$meses[\Carbon\Carbon::parse($documento->fecha)->format('m') - 1]. " del " . \Carbon\Carbon::parse($documento->fecha)->format('Y') ;
                //Salida: Miercoles 05 de Septiembre del 2016
                 
                ?>
            </p>
        @endif
    </div>
    <br><br>
    @if ($documento->nota)
        <p class="text-center">{!! str_replace(chr(10),"<br>",$documento->nota) !!}</p>
    @endif --}}

    <br><br><br><br>
    <p style="color: #fff;">.</p>


</body>
</html>
