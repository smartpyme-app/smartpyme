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
            width: 21.59cm; height: 11.6cm;
            margin-left: 0cm;
            margin-top: 0cm;
            position: relative;
        }

        #header > *, #totales > *{
            position: absolute;
            margin: 0px;
        }

        #fecha          {top: 3cm; left: 13cm; }
        #nit            {top: 3.5cm; left: 13cm; }
        #condicion      {top: 4cm; left: 13cm; }
        #cliente        {top: 3cm; left: 2.5cm; width: 9cm;}
        #direccion      {top: 3.5cm; left: 2.5cm; width: 9cm;}
        #municipio      {top: 4cm; left: 2.5cm; width: 9cm;}
        #departamento      {top: 4cm; left: 7cm; width: 9cm;}


        table   {position: absolute; top: 5cm; left: 1cm; text-align: left; border-collapse: collapse; }
        table td{height: 0.6cm; text-align: left;}

        .codigobarra{ width: 1.5cm; text-align: center;}
        .codigo{ width: 1.5cm; text-align: center;}
        .cantidad{ width: 1.5cm; text-align: center;}
        .producto{ width: 8cm; text-align: left;}
        .precio{ width: 1cm; text-align: center;}
        .descuento{ width: 1cm; text-align: center;}
        .sujetas{ width: 1.5cm; text-align: center;}
        .exentas{ width: 1cm; text-align: center;}
        .gravadas{ width: 2cm; text-align: right;}


        #letras     {top: 8.5cm; left: 2cm; width: 9cm; word-break: break-all; white-space: normal;}

        #suma       {top: 8.5cm; left: 18cm; width: 2cm; text-align: right;}
        #no_sujeta  {top: 9cm; left: 18cm; width: 2cm; text-align: right;}
        #exenta     {top: 9.5cm; left: 18cm; width: 2cm; text-align: right;}
        #total      {top: 10cm; left: 18cm; width: 2cm; text-align: right;}

        .no-print{position: absolute;}

    </style>

    <style media="print"> .no-print{display: none; } </style>

</head>
<body>
<body>

@for ($i = 0; $i < 3; $i++)
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
        <p id="suma"> $ {{ number_format($venta->total, 2) }}</p>
        <p id="total"> <b>$ {{ number_format($venta->total, 2) }}</b></p>
    </div>
</section>

@endfor

</body>
</html>
