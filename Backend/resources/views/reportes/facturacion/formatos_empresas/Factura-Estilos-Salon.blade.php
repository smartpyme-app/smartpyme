<!DOCTYPE html>
<html>
<head>
    <title>Estilos Salon {{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 11px; margin: 0; padding: 0;}
        html, body{
            width: 10.5cm; height: 14.5cm;
            font-family: serif;
        }

        #factura{
            width: 10.5cm; height: 14.5cm;
            margin-left: -0.1cm;
            margin-top: 0cm;
            position: relative;
        }

        #header > *, #totales > *{
            position: absolute;
            margin: 0px;
        }

        #fecha          {top: 3.5cm; left: 6.5cm; }
        #cliente        {top: 3.6cm; left: 1.8cm; width: 9cm;}
        #direccion      {top: 4.1cm; left: 1.8cm; width: 9cm;}
        #nit            {top: 4.6cm; left: 1.8cm; }


        table   {width: 9cm; position: absolute; top: 5.9cm; left: 0.3cm; text-align: left; border-collapse: collapse; }
        table td{height: 0.4cm; text-align: left; overflow: hidden;}

        .cantidad{ width: 1cm; text-align: center;}
        .producto {
            width: 4.2cm; 
            text-align: left; 
            word-wrap: break-word; 
            white-space: break-spaces;
            overflow-wrap: break-word;
        }
        .precio{ width: 1.3cm; text-align: center;}
        .sujetas{ width: 0.7cm; text-align: center;}
        .exentas{ width: 0.7cm; text-align: center;}
        .gravadas{ width: 1cm; text-align: right;}


        #letras     {top: 10.3cm; left: 1cm; width: 4cm; word-break: break-all; white-space: normal;}
        #correlativo{top: 17cm; left: 1cm; width: 9cm;}

        #suma       {top: 10.3cm; left: 7.5cm; width: 2cm; text-align: right;}
        #no_sujeta  {top: 11cm; left: 7.5cm; width: 2cm; text-align: right;}
        #exenta     {top: 11.3cm; left: 7.5cm; width: 2cm; text-align: right;}
        #total      {top: 12.3cm; left: 7.5cm; width: 2cm; text-align: right;}

        .no-print{position: absolute;}

    </style>

    <style media="print"> .no-print{display: none; } </style>

</head>
<body>

<section id="factura">
    <div id="header">
        <p id="fecha">{{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
        <p id="cliente">{{ $venta->nombre_cliente }}</p>
        @if ($venta->id_cliente)
            <p id="direccion">{{ $cliente->direccion }} {{ $cliente->municipio }} {{ $cliente->departamento }}</p>
        @endif
        @if ($venta->id_cliente)
            <p id="nit">{{ $cliente->dui }}</p>
        @endif
    </div>

    <table>
        @php($iva = $venta->empresa()->pluck('iva')->first() / 100)
        @foreach($venta->detalles as $detalle)
            <tr>
                <td class="cantidad">{{ number_format($detalle->cantidad, 0) }}</td>
                <td class="producto">{{$detalle->nombre_producto}}</td>
                <td class="precio">${{ number_format($detalle->precio + (($venta->iva != 0) ? ($detalle->precio * $iva) : 0), 2) }}</td>
                <td class="sujetas"> </td>
                <td class="exentas"> </td>
                <td class="gravadas">${{ number_format($detalle->total + (($venta->iva != 0)  ? ($detalle->total * $iva) : 0), 2) }}</td>
            </tr>
        @endforeach
    </table>

    <div id="totales">
        <p id="letras"> {{$dolares}} DÓLARES CON {{$centavos}} CENTAVOS.</p>
        <p id="suma"> $ {{ number_format($venta->total, 2) }}</p>
        <p id="total"> <b>$ {{ number_format($venta->total, 2) }}</b></p>
    </div>
</section>

</body>
</html>
