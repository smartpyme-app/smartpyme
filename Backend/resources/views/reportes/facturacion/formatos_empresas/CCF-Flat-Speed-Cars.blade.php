<!DOCTYPE html>
<html>
<head>
    <title> Flat Speed Cars {{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 13px; margin: 0; padding: 0;}

        #factura{
            font-family: serif;
            width: 16.2cm;
            height: 21.7cm;
            margin-left: 0cm;
            margin-top: 0.2cm;
            position: relative;
/*            border: 1px solid red;*/
        }

        #header > *, #totales > *{
            position: absolute; 
        }

        #cliente        {top: 4.7cm; left: 2.5cm; width: 9cm;}
        #direccion      {top: 5.2cm; left: 2.7cm; width: 9cm;}
        #departamento   {top: 5.7cm; left: 3.5cm; }
        
        #fecha          {top: 4.2cm; left: 11.5cm; }
        #ncr            {top: 4.7cm; left: 11.6cm; }
        #nit            {top: 5.2cm; left: 11.3cm;}
        #giro           {top: 5.7cm; left: 11.2 cm; width: 4.5cm; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;}
        #condicion      {top: 6.2cm; left: 12.5cm; }


        table   {position: absolute; top: 8.1cm; left: 0.5cm; text-align: left; border-collapse: collapse; }
        table td{height: 0.4cm; text-align: left;}

        .cantidad{  width: 1cm; text-align: center;}
        .producto{ width: 8.5cm; text-align: left;}
        .precio{ width: 1.5cm; text-align: left;}
        .sujetas{ width: 1.1cm; text-align: left;}
        .exentas{ width: 1.1cm; text-align: left;}
        .gravadas{ width: 1.5cm; text-align: right;} 


        #letras     {top: 17cm; font-size: 10px; left: 2.5cm; width: 5cm; word-break: break-all; white-space: normal;}

        #suma       {top: 16.5cm; left: 13.3cm; width: 2cm; text-align: right;}
        #iva        {top: 17cm; left: 13.3cm; width: 2cm; text-align: right;}
        #subtotal    {top: 17.5cm; left: 13.3cm; width: 2cm; text-align: right;}
        #iva_retenido     {top: 18cm; left: 13.3cm; width: 2cm; text-align: right;}
        #no_sujeta     {top: 18.5cm; left: 13.3cm; width: 2cm; text-align: right;}
        #exenta     {top: 19cm; left: 13.3cm; width: 2cm; text-align: right;}
        #total      {top: 19.5cm; left: 13.3cm; width: 2cm; text-align: right;}

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
