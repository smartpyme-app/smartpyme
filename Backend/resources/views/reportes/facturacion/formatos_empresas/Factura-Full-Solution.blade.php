<!DOCTYPE html>
<html>
<head>
    <title>Full Solutions {{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 12px; margin: 0; padding: 0;}
        html, body{
            width: 21.59cm; height: 35.56cm;
            font-family: serif;
        }

        #factura{
            width: 21.59cm; height: 17.55cm;
            margin-left: 0cm;
            margin-top: 0cm;
            position: relative;
        }

        #header > *, #totales > *{
            position: absolute;
            margin: 0px;
        }

        #header{
            margin-top: -0.3cm;
            margin-left: -0.5cm;
        }

        #fecha          {top: 3cm; left: 13cm; }
        #cliente        {top: 3cm; left: 1.7cm; width: 9cm;}
        #direccion      {top: 3.5cm; left: 1.7cm; width: 15cm;}
        #municipio      {top: 4cm; left: 1.7cm; width: 9cm;}
        #departamento   {top: 4cm; left: 7cm; width: 9cm;}
        #nit            {top: 4cm; left: 13cm; }
        #condicion      {top: 4.5cm; left: 1.7cm; }
        #vendedor      {top: 4.5cm; left: 7cm; }


        table   {position: absolute; top: 5.6cm; left: 1cm; text-align: left; border-collapse: collapse; width: 18cm; }
        table td{height: 0.4cm; text-align: left;}

        .codigobarra{ width: 1.5cm; text-align: center;}
        .codigo{ width: 1.5cm; text-align: center;}
        .cantidad{ width: 1.5cm; text-align: center;}
        .producto{ width: 8.5cm; text-align: left;}
        .precio{ width: 1cm; text-align: right;}
        .descuento{ width: 1.2cm; text-align: right;}
        .sujetas{ width: 1cm; text-align: right;}
        .exentas{ width: 1cm; text-align: right;}
        .gravadas{ width: 2cm; text-align: right;}


        #letras     {font-size:10px; top: 15.1cm;  left: 2cm; width: 11cm; word-break: break-all; white-space: normal;}

        #suma       {top: 13.6cm; left: 18cm; width: 2cm; text-align: right; font-size: 10px;}
        #iva_retenido  {top: 13.9cm; left: 18cm; width: 2cm; text-align: right; font-size: 10px;}
        #subtotal  {top: 14.2cm; left: 18cm; width: 2cm; text-align: right; font-size: 10px;}
        #no_sujeta  {top: 14.5cm; left: 18cm; width: 2cm; text-align: right; font-size: 10px;}
        #exenta     {top: 14.8cm; left: 18cm; width: 2cm; text-align: right; font-size: 10px;}
        #total      {top: 15.1cm; left: 18cm; width: 2cm; text-align: right; font-size: 10px;}

        #text_suma       {top: 13.6cm; left: 14.3cm; width: 2.5cm; text-align: right; font-size: 10px;}
        #text_iva_retenido  {top: 13.9cm; left: 14.3cm; width: 2.5cm; text-align: right; font-size: 10px;}
        #text_subtotal   {top: 14.2cm; left: 14.3cm; width: 2.5cm; text-align: right; font-size: 10px;}
        #text_no_sujeta  {top: 14.5cm; left: 14.3cm; width: 2.5cm; text-align: right; font-size: 10px;}
        #text_exenta     {top: 14.8cm; left: 14.3cm; width: 2.5cm; text-align: right; font-size: 10px;}
        #text_total      {top: 15.1cm; left: 14.3cm; width: 2.5cm; text-align: right; font-size: 10px;}


        .no-print{position: absolute;}

    </style>

    <style media="print"> .no-print{display: none; } </style>

</head>
<body>
<body>

<section id="factura">
    <div id="header">
        <p id="fecha"><b>Fecha:</b> {{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
        <p id="cliente"><b>Cliente:</b> {{ $venta->nombre_cliente }}</p>
        @if ($venta->id_cliente)
            <p id="direccion"><b>Dirección:</b> {{ $cliente->direccion }}</p>
            <p id="municipio"><b>Municipio:</b> {{ $cliente->municipio }}</p>
            <p id="departamento"><b>Departamento:</b> {{ $cliente->departamento }}</p>
        @endif
        @if($venta->estado == 'Pagada')
            <p id="condicion"><b>Condición: </b>CONTADO</p>
        @elseif($venta->estado == 'Pendiente')
            <p id="condicion"><b>Condición: </b>CREDITO a {{ \Carbon\Carbon::parse($venta->fecha)->diffInDays(\Carbon\Carbon::parse($venta->fecha_pago), false) }} días</p>
        @endif
        @if ($venta->id_cliente)
            <p id="nit"><b>DUI:</b> {{ $cliente->dui }}</p>
        @endif
            <p id="vendedor"><b>Vendedor: </b>{{ $venta->nombre_vendedor }}</p>
    </div>

    <table>
        @php($iva = $venta->empresa()->pluck('iva')->first() / 100)
        @foreach($venta->detalles as $detalle)
            <tr>
                <td class="codigobarra">     {{ $detalle->producto()->pluck('barcode')->first() }}</td>
                <td class="codigo">     {{ $detalle->producto()->pluck('codigo')->first() }}</td>
                <td class="cantidad">   {{ number_format($detalle->cantidad, 0) }}</td>
                <td class="producto">   {{ $detalle->nombre_producto  }}</td>
                <td class="precio">     ${{ number_format($detalle->precio + (($venta->iva != 0) ? ($detalle->precio * $iva) : 0), 2) }}</td>
                <td class="descuento">
                    @if ($detalle->descuento > 0)
                        ${{ number_format($detalle->descuento, 2) }}  
                     @endif 
                </td>
                <td class="sujetas">   </td>
                <td class="exentas">    </td>
                <td class="gravadas">  ${{ number_format($detalle->total + (($venta->iva != 0)  ? ($detalle->total * $iva) : 0), 2) }} </th>
            </tr>
        @endforeach
    </table>

    <div id="totales">
        <p id="letras"> {{$dolares}} DÓLARES CON {{$centavos}} CENTAVOS.</p>
        <p id="text_suma">SUMAS</p>
        <p id="suma"> $ {{ number_format($venta->total, 2) }}</p>
        <p id="text_subtotal">SUB-TOTAL</p>
        <p id="subtotal"> $ {{ number_format($venta->total, 2) }}</p>
        <p id="text_iva_retenido">(-) IVA RETENIDO</p>
            @if($venta->iva_retenido > 0)
                <p id="iva_retenido"> ${{ number_format($venta->iva_retenido, 2) }}</p>
            @endif
        <p id="text_no_sujeta">NO SUJETAS</p>
            @if($venta->no_sujeta > 0)
            <p id="no_sujeta"> ${{ number_format($venta->no_sujeta, 2) }}</p>
            @endif
        <p id="text_exenta">EXENTAS</p>
        @if($venta->exenta > 0)
            <p id="exenta"> ${{ number_format($venta->exenta, 2) }}</p>
        @endif
        <p id="total"> <b>$ {{ number_format($venta->total, 2) }}</b></p>
    </div>
</section>

<section id="factura" style="margin-top: -0.7cm;">
    <div id="header">
        <p id="fecha"><b>Fecha:</b> {{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
        <p id="cliente"><b>Cliente:</b> {{ $venta->nombre_cliente }}</p>
        @if ($venta->id_cliente)
            <p id="direccion"><b>Dirección:</b> {{ $cliente->direccion }}</p>
            <p id="municipio"><b>Municipio:</b> {{ $cliente->municipio }}</p>
            <p id="departamento"><b>Departamento:</b> {{ $cliente->departamento }}</p>
        @endif
        @if($venta->estado == 'Pagada')
            <p id="condicion"><b>Condición: </b>CONTADO</p>
        @elseif($venta->estado == 'Pendiente')
            <p id="condicion"><b>Condición: </b>CREDITO</p>
        @endif
        @if ($venta->id_cliente)
            <p id="nit"><b>DUI:</b> {{ $cliente->dui }}</p>
        @endif
    </div>

    <table>
        @php($iva = $venta->empresa()->pluck('iva')->first() / 100)
        @foreach($venta->detalles as $detalle)
            <tr>
                <td class="codigobarra">     {{ $detalle->producto()->pluck('barcode')->first() }}</td>
                <td class="codigo">     {{ $detalle->producto()->pluck('codigo')->first() }}</td>
                <td class="cantidad">   {{ number_format($detalle->cantidad, 0) }}</td>
                <td class="producto">   {{ $detalle->nombre_producto  }}</td>
                <td class="precio">     ${{ number_format($detalle->precio + (($venta->iva != 0) ? ($detalle->precio * $iva) : 0), 2) }}</td>
                <td class="descuento">
                    @if ($detalle->descuento > 0)
                        ${{ number_format($detalle->descuento, 2) }}  
                     @endif 
                </td>
                <td class="sujetas">   </td>
                <td class="exentas">    </td>
                <td class="gravadas">  ${{ number_format($detalle->total + (($venta->iva != 0)  ? ($detalle->total * $iva) : 0), 2) }} </th>
            </tr>
        @endforeach
    </table>

    <div id="totales">
        <p id="letras"> {{$dolares}} DÓLARES CON {{$centavos}} CENTAVOS.</p>
        <p id="text_suma">SUMAS</p>
        <p id="suma"> $ {{ number_format($venta->total, 2) }}</p>
        <p id="text_subtotal">SUB-TOTAL</p>
        <p id="subtotal"> $ {{ number_format($venta->total, 2) }}</p>
        <p id="text_iva_retenido">(-) IVA RETENIDO</p>
            @if($venta->iva_retenido > 0)
                <p id="iva_retenido"> ${{ number_format($venta->iva_retenido, 2) }}</p>
            @endif
        <p id="text_no_sujeta">NO SUJETAS</p>
            @if($venta->no_sujeta > 0)
            <p id="no_sujeta"> ${{ number_format($venta->no_sujeta, 2) }}</p>
            @endif
        <p id="text_exenta">EXENTAS</p>
        @if($venta->exenta > 0)
            <p id="exenta"> ${{ number_format($venta->exenta, 2) }}</p>
        @endif
        <p id="total"> <b>$ {{ number_format($venta->total, 2) }}</b></p>
    </div>
</section>

</body>
</html>
