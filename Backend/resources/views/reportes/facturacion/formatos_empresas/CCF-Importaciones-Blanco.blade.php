<!DOCTYPE html>
<html>
<head>
    <title> Importaciones Blanco {{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 13px; margin: 0; padding: 0;}

        #factura{
            font-family: serif;
            width: 14cm;
            height: 21cm;
            margin-left: 0cm;
            margin-top: 0cm;
            position: relative;
/*            border: 1px solid red;*/
        }

        #header > *, #totales > *{
            position: absolute; 
        }

        #cliente        {top: 4cm; left: 2.5cm; width: 9cm;}
        #direccion      {top: 4.5cm; left: 2.7cm; width: 9cm;}
        #departamento   {top: 5.5cm; left: 3cm; }
        
        #fecha          {top: 3.5cm; left: 9cm; }
        #nit            {top: 4cm; left: 9cm;}
        #ncr            {top: 4.5cm; left: 9cm; }
        #giro           {top: 5cm; left: 8.5cm; width: 4.5cm; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;}
        #condicion      {top: 5.5cm; left: 10cm; }


        table   {position: absolute; top: 7.5cm; left: 0.4cm; text-align: left; border-collapse: collapse; }
        table td{height: 0.4cm; text-align: left;}

        .cantidad{  width: 1.4cm; text-align: center;}
        .producto{ width: 6cm; text-align: left;}
        .precio{ width: 1.2cm; text-align: center;}
        .sujetas{ width: 1cm; text-align: center;}
        .exentas{ width: 1cm; text-align: center;}
        .gravadas{ width: 1.5cm; text-align: right;} 


        #letras     {top: 16cm; font-size: 10px; left: 2.5cm; width: 5cm; word-break: break-all; white-space: normal;}

        #suma       {top: 16cm; left: 11cm; width: 2cm; text-align: right;}
        #iva        {top: 16.5cm; left: 11cm; width: 2cm; text-align: right;}
        #subtotal    {top: 17cm; left: 11cm; width: 2cm; text-align: right;}
        #iva_retenido     {top: 17.5cm; left: 11cm; width: 2cm; text-align: right;}
        #no_sujeta     {top: 18cm; left: 11cm; width: 2cm; text-align: right;}
        #exenta     {top: 18.5cm; left: 11cm; width: 2cm; text-align: right;}
        #total      {top: 19cm; left: 11cm; width: 2cm; text-align: right;}

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
