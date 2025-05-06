<!DOCTYPE html>
<html>
<head>
    {{-- <script language="javascript">setTimeout("self.close();",500)</script> --}}
    <title>Zoe Cosmetics {{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 14px; margin: 0; padding: 0;}
        html, body{
            font-family: serif;
        }

        #factura{
            width: 15.5cm; height: 25.5cm;
            margin-left: 0cm;
            margin-top: 0cm;
            position: relative;
        }

        #header > *, #totales > *{
            position: absolute;
            margin: 0px;
        }

        #fecha          {top: 4.5cm; left: 4cm; }

        #cliente        {top: 5cm; left: 5.6cm; width: 9cm;}
        #direccion      {top: 6cm; left: 3cm; width: 7.2cm;}
        #telefono       {top: 6cm; left: 11cm; width: 4cm;}
        #nit            {top: 6.8cm; left: 6cm; }


        table   {position: absolute; top: 8.5cm; left: 1cm; text-align: left; border-collapse: collapse; width: 13.5cm;}
        table td{height: 1cm; text-align: left;}

        .cantidad{ width: 1.8cm; text-align: center;}
        .producto{ width: 7.2cm; text-align: left;}
        .precio{ width: 2cm; text-align: center;}
        .gravadas{ width: 2cm; text-align: right;}
        

        #letras     {top: 19cm; left: 2cm; width: 6cm; word-break: break-all; white-space: normal;}

        #suma       {top: 18.5cm; left: 12.5cm; width: 2cm; text-align: right;}
        #renta      {top: 19.5cm; left: 12.5cm; width: 2cm; text-align: right;}
        #subtotal   {top: 20.5cm; left: 12.5cm; width: 2cm; text-align: right;}
        #total      {top: 21.5cm; left: 12.5cm; width: 2cm; text-align: right;}

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
                <p id="direccion">{{ $cliente->direccion }} {{ $cliente->municipio }} {{ $cliente->departamento }}</p>
                <p id="nit">{{ $cliente->dui }}</p>
                <p id="telefono">{{ $cliente->telefono }}</p>
            @endif
        </div>
                    
        <table>
            @php($iva = $venta->empresa()->pluck('iva')->first() / 100)
            @foreach($venta->detalles as $detalle)
            <tr>
                <td class="cantidad">   {{ number_format($detalle->cantidad, 0) }}</td>
                <td class="producto">   {{ $detalle->nombre_producto  }}</td>
                <td class="precio">     ${{ number_format($detalle->precio + (($venta->iva != 0) ? ($detalle->precio * $iva) : 0), 2) }}</td>
                <td class="gravadas">  ${{ number_format($detalle->total + (($venta->iva != 0)  ? ($detalle->total * $iva) : 0), 2) }} </th>
            </tr>
            @endforeach
        </table>

        <div id="totales">
            <p id="letras"> {{$dolares}} DÓLARES CON {{$centavos}} CENTAVOS.</p>

            <p id="suma"> $ {{ number_format($venta->sub_total, 2) }}</p>
            <p id="renta"> $ {{ number_format($venta->renta_retenida, 2) }}</p>
            <p id="subtotal"> $ {{ number_format($venta->sub_total - $venta->renta_retenida, 2) }}</p>
            <p id="total"> <b>$ {{ number_format($venta->total, 2) }}</b></p>
        </div>
    </section>

</body>
</html>
