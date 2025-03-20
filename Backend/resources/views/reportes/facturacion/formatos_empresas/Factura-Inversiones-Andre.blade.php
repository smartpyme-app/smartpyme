<!DOCTYPE html>
<html>
<head>
    {{-- <script language="javascript">setTimeout("self.close();",500)</script> --}}
    <title>Inversiones Andre {{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 14px!important; margin: 0; padding: 0;}
        html, body{
            font-family: serif;
        }

        #factura{
/*            width: 21.60cm; height: 27.85cm;*/
            position: relative;
        }

        #header > *, #totales > *{
            position: absolute;
            margin: 0px;
        }

        #fecha          {top: 6.3cm; left: 15cm;}
        #idcliente      {top: 7.3cm; left: 15cm;}

        #cliente        {top: 8.6cm; left: 3cm;}
        #direccion      {top: 9.1cm; left: 3cm;}
        #telefono       {top: 9.1cm; left: 15cm; }

        table   {position: absolute; top: 13cm; left: 0.7cm; text-align: left; border-collapse: collapse; width: 17.5cm; }
        table td{height: 0.5cm; text-align: left;}

        .cantidad{ width: 1cm; text-align: center;}
        .codigo{ width: 2cm; text-align: center;}
        .producto{ width: 9.9cm; text-align: left;}
        .precio{ width: 2.5cm; text-align: right;}
        .gravadas{ width: 2.5cm; text-align: right;}

        #suma       {top: 19.9cm; left: 16.5cm; width: 2cm; text-align: right;}
        #iva        {top: 22.1cm; left: 16.5cm; width: 2cm; text-align: right;}
/*        #sub_total  {top: 15cm; left: 16.5cm; width: 2cm; text-align: right;}*/
        #total      {top: 23.2cm; left: 16.5cm; width: 2cm; text-align: right;}

        .no-print{position: absolute;}

    </style>

    <style media="print"> .no-print{display: none; } </style>

</head>
<body>
<body>  

    <section id="factura">
        <div id="header">
            <p id="fecha">{{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
            <p id="idcliente">{{ $venta->id_cliente }}</p>
            <p id="cliente">{{ $venta->nombre_cliente }}</p>
            @if ($venta->id_cliente)
                <p id="direccion">{{ $cliente->direccion }}</p>
                <p id="telefono">{{ $cliente->telefono }}</p>
            @endif
        </div>

        <table>
            @php($iva = $venta->empresa()->pluck('iva')->first() / 100)
            @foreach($venta->detalles as $detalle)
            <tr>
                <td class="cantidad">   {{ number_format($detalle->cantidad, 0) }}</td>
                <td class="codigo">   {{ $detalle->producto->codigo  }}</td>
                <td class="producto">   {{ $detalle->nombre_producto  }}</td>
                <td class="precio">     ${{ number_format($detalle->precio, 2) }}</td>
                <td class="gravadas">  ${{ number_format($detalle->total, 2) }} </td> 
            </tr>
            @endforeach
        </table>

        <div id="totales">
            <p id="suma"> $ {{ number_format($venta->sub_total, 2) }}</p>
            <p id="iva"> $ {{ number_format($venta->iva, 2) }}</p>
            {{-- <p id="sub_total"> $ {{ number_format($venta->sub_total + $venta->iva, 2) }}</p> --}}
            <p id="total"> <b>$ {{ number_format($venta->total, 2) }}</b></p>
        </div>
    </section>

</body>
</html>
