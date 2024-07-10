<!DOCTYPE html>
<html>
<head>
    <title>KEKE {{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 12px; margin: 0; padding: 0;}
        html, body{
            width: 23.5cm; height: 21.5cm;
            font-family: serif;
            /*            border: 1px solid red;*/
        }

        #factura{
            margin-left: 0cm;
            margin-top: 0cm;
            position: relative;
        }

        #header > *, #totales > *{
            position: absolute;
            margin: 0px;
        }

        #fecha          {top: 3.5cm; left: 13cm; }
        #nit            {top: 5cm; left: 16cm; }
        #condicion      {top: 5cm; left: 12.8cm; }
        #cliente        {top: 3.5cm; left: 2.5cm; width: 9cm;}
        #direccion      {top: 4.2cm; left: 2.5cm; width: 9cm;}
        #municipio      {top: 5cm; left: 2.5cm; width: 9cm;}
        #departamento   {top: 5.5cm; left: 2.5cm; width: 9cm;}


        table   {position: absolute; top: 6.2cm; left: 0.6cm; text-align: left; border-collapse: collapse; }
        table td{height: 0.6cm; text-align: left;}

        .cantidad{ width: 1.3cm; text-align: center;}
        .codigo{ width: 2.7cm; text-align: left;}
        .producto{ width: 8.9cm; text-align: left;}
        .precio{ width: 1.5cm; text-align: center;}
        .sujetas{ width: 1.4cm; text-align: center;}
        .exentas{ width: 1.4cm; text-align: center;}
        .gravadas{ width: 2cm; text-align: right;}


        #letras     {top: 16.5cm; left: 2cm; width: 9cm; word-break: break-all; white-space: normal;}
        #correlativo{top: 17cm; left: 2cm;; width: 9cm;}

        #suma       {top: 16.6cm; left: 18cm; width: 2cm; text-align: right;}
        #no_sujeta  {top: 12.5cm; left: 18cm; width: 2cm; text-align: right;}
        #exenta     {top: 13cm; left: 18cm; width: 2cm; text-align: right;}
        #total      {top: 19cm; left: 18cm; width: 2cm; text-align: right;}

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
            <p id="direccion">{{ $cliente->direccion }}</p>
        @endif
        @if($venta->estado == 'Pagada')
            <p id="condicion">CONTADO</p>
        @elseif($venta->estado == 'Pendiente')
            <p id="condicion">CREDITO</p>
        @endif
        @if ($venta->id_cliente)
            <p id="nit">{{ $cliente->dui }}</p>
        @endif
    </div>

    <table>
        @php($iva = $venta->empresa()->pluck('iva')->first() / 100)
        @foreach($venta->detalles as $detalle)
            <tr>
                <td class="cantidad">   {{ number_format($detalle->cantidad, 0) }}</td>
                <td class="codigo">     {{ $detalle->producto()->pluck('codigo')->first() }}</td>
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
        <p id="total"> <b>$ {{ number_format($venta->total, 2) }}</b></p>
    </div>
</section>

</body>
</html>
