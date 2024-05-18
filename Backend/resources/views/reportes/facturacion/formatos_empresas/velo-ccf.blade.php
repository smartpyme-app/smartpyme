<!DOCTYPE html>
<html>
<head>
    <title>Velo {{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 10px; margin: 0; padding: 0;}
        html, body{
            width: 23.5cm; height: 21.5cm;
            font-family: serif;
/*            border: 1px solid red;*/
        }

        #factura{
            margin-left: 0cm;
            margin-top: -2cm;
            position: relative;
        }

        #header > *, #totales > *{
            position: absolute;
            margin: 0px;
        }

        #fecha          {top: 5cm; left: 14.5cm; }
        #ncr           {top: 5.3cm; left: 14.5cm; }
        #nit            {top: 5.6cm; left: 14.5cm; }
        #giro            {top: 5.9cm; left: 14.5cm; }

        #cliente        {top: 5cm; left: 2.5cm; width: 9cm;}
        #direccion      {top: 5.3cm; left: 2.5cm; width: 9cm;}
        #municipio      {top: 5.6cm; left: 2.5cm; width: 9cm;}
        #condicion      {top: 5.6cm; left: 6.5cm; }
        #departamento      {top: 6.5cm; left: 2.5cm; width: 9cm;}


        table   {position: absolute; top: 7.5cm; left: 0.5cm; text-align: left; border-collapse: collapse; }
        table td{height: 0.6cm; text-align: left;}

        .cantidad{  width: 1.5cm; text-align: center;}
        .codigo{ width: 2.7cm; text-align: left;}
        .producto{ width: 9.5cm; text-align: left;}
        .precio{ width: 1.5cm; text-align: center;}
        .sujetas{ width: 1.2cm; text-align: center;}
        .exentas{ width: 1.2cm; text-align: center;}
        .gravadas{ width: 2cm; text-align: right;}


        #letras     {top: 13cm; left: 2cm; width: 9cm; word-break: break-all; white-space: normal;}
        #correlativo{top: 17cm; left: 2cm;; width: 9cm;;}

        #suma       {top: 13cm; left: 18cm; width: 2cm; text-align: right;}
        #iva        {top: 13.3cm; left: 18cm; width: 2cm; text-align: right;}
        #subtotal    {top: 13.6cm; left: 18cm; width: 2cm; text-align: right;}
        #no_sujeta  {top: 13.9cm; left: 18cm; width: 2cm; text-align: right;}
        #iva_retenido     {top: 14.1cm; left: 18cm; width: 2cm; text-align: right;}
        #no_sujetas     {top: 14.4cm; left: 18cm; width: 2cm; text-align: right;}
        #exenta     {top: 14.7cm; left: 18cm; width: 2cm; text-align: right;}
        #total      {top: 14cm; left: 18cm; width: 2cm; text-align: right;}

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
            <p id="municipio">{{ $cliente->municipio }}</p>
            {{-- <p id="departamento">{{ $cliente->departamento }}</p> --}}
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
                <td class="codigo">     {{ $detalle->producto()->pluck('codigo')->first() }}</td>
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
            <p id="subtotal"> $ {{ number_format($venta->sub_total, 2) }}</p>
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
