<!DOCTYPE html>
<html>
<head>
    <title> KEKE {{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 11px; margin: 0; padding: 0;}
        html, body{
            width: 11.5cm; height: 16.5cm;
            font-family: serif;
/*            border: 1px solid red;*/
        }

        #factura{
            margin-left: 2cm;
            margin-top: 0cm;
            position: relative;
        }

        #header > *, #totales > *{
            position: absolute;
            margin: 0px;
        }
        /*top si subo medida baja altura en factura*/
        #fecha          {top: 3.5cm; left: 7cm; }
        #ncr           {top: 4cm; left: 7.5cm; }
        #nit            {top: 4.5cm; left: 6.7cm; }
        #condicion      {top: 4.8cm; left: 8.2cm; }
        #giro            {top: 5.2cm; left: 6.7cm; }

        #cliente        {top: 3.5cm; left: 1.6cm; width: 9cm;}
        #direccion      {top: 4cm; left: 2cm; width: 9cm;}
        #departamento      {top: 4.5cm; left: 2.3cm; width: 9cm;}


        table   {position: absolute; top: 6.3cm; left: 0.5cm; text-align: left; border-collapse: collapse; }
        table td{height: 0.4cm; text-align: left;}

        .cantidad{  width: 1.2cm; text-align: center;}
        .producto{ width: 4cm; text-align: left;}
        .precio{ width: 1.0cm; text-align: center;}
        .sujetas{ width: 1.0cm; text-align: center;}
        .exentas{ width: 1.0cm; text-align: center;}
        .gravadas{ width: 1.5cm; text-align: right;}


        #letras     {top: 12.5cm; font-size: 10px; left: 2.5cm; width: 5cm; word-break: break-all; white-space: normal;}
        #correlativo{top: 17cm; left: 2cm;; width: 9cm;}

        #suma       {top: 12cm; left: 9.5cm; width: 2cm; text-align: left;}
        #iva        {top: 12.4cm; left: 9.5cm; width: 2cm; text-align: left;}
        #subtotal    {top: 12.8cm; left: 9.5cm; width: 2cm; text-align: left;}
        #iva_retenido     {top: 13.1cm; left: 9.5cm; width: 2cm; text-align: left;}
        #no_sujeta     {top: 13.5cm; left: 9.5cm; width: 2cm; text-align: left;}
        #exenta     {top: 14cm; left: 9.5cm; width: 2cm; text-align: left;}
        #total      {top: 14.2cm; left: 9.5cm; width: 2cm; text-align: left;}

        .no-print{position: absolute;}

    </style>

    <style media="print"> .no-print{display: none; } </style>

</head>
<body>
<body>

<section id="factura">
    <div id="header">
        <p id="fecha">{{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
        <p id="cliente">{{ $venta->nombre_cliente }}</p>
        @if ($venta->id_cliente)
            @if($cliente->direccion != null)
            <p id="direccion">{{ $cliente->direccion }}</p>
            @else
                <p id="direccion">{{ $cliente->empresa_direccion }}</p>
            @endif
            {{-- <p id="municipio">{{ $cliente->municipio }}</p> --}}
             <p id="departamento">{{ $cliente->departamento }}</p>
        @endif
        @if($venta->estado == 'Pagada')
            <p id="condicion">CONTADO</p>
        @elseif($venta->estado == 'Pendiente')
            <p id="condicion">CREDITO</p>
        @endif
        @if ($venta->id_cliente)
            <p id="ncr">{{ $cliente->ncr }}</p>
            <p id="nit">{{ $cliente->nit }}</p>
            <p id="giro">{{ $cliente->giro }}</p>
        @endif
    </div>

    <table>
        @php($iva = $venta->empresa()->pluck('iva')->first() / 100)
        @foreach($venta->detalles as $detalle)
            <tr>
                <td class="cantidad">   {{ number_format($detalle->cantidad, 0) }}</td>
                <td class="producto">   {{ $detalle->nombre_producto  }}</td>
                <td class="precio">     ${{ number_format($detalle->precio, 2) }}</td>
                <td class="sujetas">   </td>
                <td class="exentas">    </td>
                <td class="gravadas">  ${{ number_format($detalle->total, 2) }} </th>
            </tr>
        @endforeach
    </table>

    <div id="totales">
        <p id="letras"> {{$dolares}} DÓLARES CON {{$centavos}} CENTAVOS.</p>
        <p id="suma"> $ {{ number_format($venta->sub_total, 2) }}</p>
        <p id="iva"> $ {{ number_format($venta->iva, 2) }}</p>
        <p id="subtotal"> $ {{ number_format($venta->total + $venta->iva_retenido, 2) }}</p>
        @if($venta->iva_retenido > 0)
            <p id="iva_retenido"> $ {{ number_format($venta->iva_retenido, 2) }}</p>
        @endif
        @if($venta->no_sujeta > 0)
            <p id="no_sujeta"> $ {{ number_format($venta->no_sujeta, 2) }}</p>
        @endif
        @if($venta->exenta > 0)
            <p id="exenta"> $ {{ number_format($venta->exenta, 2) }}</p>
        @endif
        <p id="total"> <b>$ {{ number_format($venta->total, 2) }}</b></p>
    </div>
</section>

</body>
</html>
