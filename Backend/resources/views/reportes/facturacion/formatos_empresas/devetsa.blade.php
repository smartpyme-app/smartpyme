<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    {{-- <script language="javascript">setTimeout("self.close();",500)</script> --}}
    <title>{{$venta->documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 12px; margin: 0; padding: 0;}
        html, body {
            width: 21.59cm; height: 13.97cm;
            font-family: serif;
            margin-top: -0.5cm;
        }

        #factura{
            background-image: url('/img/factura.jpg'); background-size: 100%; 
            width: 100%; height: 100%;
        }

        #header, #totales{
            position: relative;
        }

        #header > *, #totales > *{
            position: absolute;
            margin: -2px 0px 0px 0px;
/*            overflow: hidden; white-space: pre;*/
        }

        #cliente        {top: 3.5cm; left: 2.5cm; width: 9cm;}
        #direccion      {top: 4cm; left: 2.5cm; }

        #fecha          {top: 3.5cm; left: 16.5cm; }
        #nit            {top: 5cm; left: 2.5cm; }

        table   {position: absolute; top: 6.1cm; left: 0.5cm; text-align: left; border-collapse: collapse; }
        table td{height: 0.5cm;}

        .cantidad{ width: 2cm; text-align: center;}
        .producto{ width: 12.5cm;}
        .precio{ width: 1.7cm; text-align: center;}
        .sujetas{ width: 1cm; text-align: center;}
        .exentas{ width: 1cm; text-align: center;}
        .gravadas{ width: 1.8cm; text-align: right;}
        

        #letras     {top: 10cm; left: 2.5cm; width: 9cm; word-break: break-all; white-space: normal;}
        #correlativo{top: 10.5cm; left: 2cm;; width: 9cm;;}
        #info       {top: 9.2cm; left: 3cm; width: 9cm;;}

        #suma       {top: 10cm; left: 18.5cm; width: 2cm; text-align: right;}
        #no_sujeta  {top: 11cm; left: 18.5cm; width: 2cm; text-align: right;}
        #exenta     {top: 11.5cm; left: 18.5cm; width: 2cm; text-align: right;}
        #total      {top: 12.5cm; left: 18.5cm; width: 2cm; text-align: right;}

        .no-print{position: absolute;}

    </style>
    
    <style media="print"> .no-print{display: none; } </style>

</head>
<body>
<body>

    <section id="factura">
        <div id="header">
            <p id="fecha">{{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
            <p id="cliente">{{ $venta->cliente }}</p>
            <p id="direccion">{{ $cliente->direccion }}</p>
            <p id="nit">{{ $cliente->nit }}</p>
        </div>
                    
        <table>
            @php($iva = $venta->empresa()->pluck('iva')->first() / 100)
            @foreach($venta->detalles as $detalle)
            <tr>
                <td class="cantidad">   {{ number_format($detalle->cantidad, 2) }}</td>
                <td class="producto">   {{ $detalle->producto  }}</td>
                <td class="precio">     ${{ number_format($detalle->precio + (($venta->iva != 0) ? ($detalle->precio * $iva) : 0), 2) }}</td>
                <td class="sujetas">   </td>
                <td class="exentas">    </td>
                <td class="gravadas">  ${{ number_format($detalle->total + (($venta->iva != 0)  ? ($detalle->total * $iva) : 0), 2) }} </th>
            </tr>
            @endforeach
        </table>

        <div id="totales">
            <p id="letras"> {{$dolares}} DÓLARES CON {{$centavos}} CENTAVOS.</p>
            {{-- <p id="correlativo">{{ $venta->correlativo }}</p> --}}

            <p id="suma"> $ {{ number_format($venta->total, 2) }}</p>
            {{-- <p id="iva"> $ {{ number_format($venta->iva, 2) }}</p> --}}
            <p id="total"> <b>$ {{ number_format($venta->total, 2) }}</b></p>
        </div>
    </section>

</body>
</html>
