<!DOCTYPE html>
<html>
<head>
    <title>Clínica {{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 13px; margin: 0; padding: 0;}
        html, body{
            font-family: serif;
        }

        #factura{
            width: 13.7cm; height: 21.4cm;
            margin-left: 0cm;
            margin-top: 0cm;
            position: relative;
            border: 1px solid red;
        }

        #header > *, #totales > *{
            position: absolute;
            margin: 0px;
        }

        #cliente        {top: 5cm; left: 2.5cm; width: 6.5cm;}
        #direccion      {top: 5.6cm; left: 2.5cm; width: 6.5cm;}

        #fecha          {top: 5cm; left: 9.5cm; }
        #nit            {top: 5.5cm; left: 10cm; }
        #condicion      {top: 6cm; left: 12.3cm; }


        table   {position: absolute; top: 7cm; left: 0.5cm; text-align: left; border-collapse: collapse; width: 12.1cm}
        table td{height: 0.6cm; text-align: left;}

        .cantidad{ width: 1cm; text-align: center;}
        .producto{ width: 7cm; text-align: left;}
        .precio{ width: 1.5cm; text-align: center;}
        .sujetas{ width: 0.5cm; text-align: center;}
        .exentas{ width: 0.5cm; text-align: center;}
        .gravadas{ width: 1.5cm; text-align: right;}
        

        #letras     {font-size: 11px; top: 17cm; left: 1.5cm; width: 7cm; word-break: break-all; white-space: normal;}

        #suma       {top: 17cm; left: 10.8cm; width: 2cm; text-align: right;}
        #ivaretenido{top: 17.5cm; left: 10.8cm; width: 2cm; text-align: right;}
        #subtotal   {top: 18cm; left: 10.8cm; width: 2cm; text-align: right;}
        #no_sujeta  {top: 18.5cm; left: 10.8cm; width: 2cm; text-align: right;}
        #exenta     {top: 19cm; left: 10.8cm; width: 2cm; text-align: right;}
        #total      {top: 19.5cm; left: 10.8cm; width: 2cm; text-align: right;}

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
                <p id="direccion">{{ $cliente->direccion }} {{ $cliente->municipio }}</p>
                <p id="nit">{{ $cliente->dui }}</p>
            @endif
            <p id="condicion">
                @if ($venta->estado == 'Pagada')
                    X
                @else
                    <span style="margin-left: -2cm;">X</span>
                @endif
            </p>
        </div>
                    
        <table>
            @php($iva = $venta->empresa()->pluck('iva')->first() / 100)
            @foreach($venta->detalles as $detalle)
            <tr>
                <td class="cantidad">   {{ number_format($detalle->cantidad, 0) }}</td>
                <td class="producto">   {{ $detalle->nombre_producto  }}</td>
                <td class="precio">     ${{ number_format($detalle->precio + (($venta->iva != 0) ? ($detalle->precio * $iva) : 0), 2) }}</td>
                <td class="sujetas">   </td>
                <td class="exentas">    </td>
                <td class="gravadas">  ${{ number_format($detalle->total + (($venta->iva != 0)  ? ($detalle->total * $iva) : 0), 2) }} </th>
            </tr>
            @endforeach
        </table>

        <div id="totales">
            <p id="letras"> {{$dolares}} DÓLARES CON {{$centavos}} CENTAVOS.</p>

            <p id="suma"> $ {{ number_format($venta->total, 2) }}</p>
            {{-- <p id="iva"> $ {{ number_format($venta->iva, 2) }}</p> --}}
            <p id="total"> <b>$ {{ number_format($venta->total, 2) }}</b></p>
        </div>
    </section>

</body>
</html>
