<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    {{-- <script language="javascript">setTimeout("self.close();",500)</script> --}}
    <title>Destroyesa {{$venta->documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 12px; margin: 0; padding: 0;}
        html, body {
            width: 10.5cm; height: 13.5cm;
            font-family: serif;
            margin-top: 0cm;
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
            margin: 0px 0px 0px 0px;
/*            overflow: hidden; white-space: pre;*/
        }

        #fecha          {top: 3cm; left: 6.5cm; }
        #cliente        {top: 3.5cm; left: 2cm; width: 9cm;}
        #direccion      {top: 4cm; left: 2.5cm; }
        #departamento   {top: 5.2cm; left: 3cm; }
        #nit            {top: 4.7cm; left: 6cm; }
        #nrc            {top: 5cm; left: 7cm; }
        #giro           {top: 5cm; left: 7cm; }
        #condicion      {top: 5.5cm; left: 6cm;}


        table   {position: absolute; top: 6.5cm; left: 0.6cm; text-align: left; border-collapse: collapse; }
        table td{height: 0.5cm;}

        .cantidad{ width: 1.3cm; text-align: center;}
        .producto{ width: 3.8cm;}
        .precio{ width: 1cm; text-align: center;}
        .sujetas{ width: 0.7cm; text-align: center;}
        .exentas{ width: 0.7cm; text-align: center;}
        .gravadas{ width: 1.5cm; text-align: right;}
        

        #letras     {top: 9.6cm; left: 1.5cm; width: 4.2cm; font-size: 10px; word-break: break-all; white-space: normal;}
        #correlativo{top: 10.5cm; left: 2cm;; width: 5cm;;}
        #info       {top: 9.2cm; left: 3cm; width: 9cm;;}

        #suma       {top: 10cm; left: 8.7cm; width: 1cm; text-align: right;}
        #iva        {top: 10.5cm; left: 8.7cm; width: 1cm; text-align: right;}
        #iva_retenido{top: 10.5cm; left: 8.7cm; width: 1cm; text-align: right;}
        #no_sujeta  {top: 11cm; left: 8.7cm; width: 1cm; text-align: right;}
        #exenta     {top: 11.5cm; left: 8.7cm; width: 1cm; text-align: right;}
        #total      {top: 12.2cm; left: 8.7cm; width: 1cm; text-align: right;}

        .no-print{position: absolute;}

    </style>
    
    <style media="print"> .no-print{display: none; } </style>

</head>
<body>
<body onload="javascript:print();" style="margin-left: 0cm; margin-top: 0cm">

    <section id="factura">
        <div id="header">
            <p id="fecha">{{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
            <p id="cliente">{{ $venta->cliente }}</p>
            <p id="direccion">{{ $cliente->direccion }}</p>
            <p id="departamento">{{ $cliente->departamento }}</p>
            <p id="nit">{{ $cliente->nit }}</p>
            <p id="nrc">{{ $cliente->ncr }}</p>
            <p id="giro">{{ \Illuminate\Support\Str::limit($cliente->giro, 20, $end = '...') }}</p>
            <p id="condicion"> @if ($venta->estado == "Pendiente") Credito @else Contado @endif
            </p>
        </div>
                    
        <table>
            @foreach($venta->detalles as $detalle)
            <tr>
                <td class="cantidad">   {{ number_format($detalle->cantidad, 2) }}</td>
                <td class="producto">   {{ $detalle->producto  }}</td>
                <td class="precio">     $ {{ number_format($detalle->precio , 2) }}</td>
                <td class="sujetas">   </td>
                <td class="exentas">    </td>
                <td class="gravadas">  $ {{ number_format($detalle->total, 2) }} </th>
            </tr>
            @endforeach
        </table>

        <div id="totales">
            <p id="letras"> {{$dolares}} DÓLARES CON {{$centavos}} CENTAVOS.</p>
            {{-- <p id="correlativo">{{ $venta->correlativo }}</p> --}}

            <p id="suma"> $ {{ number_format($venta->sub_total, 2) }}</p>
            <p id="iva"> $ {{ number_format($venta->iva, 2) }}</p>
            <p id="total"> <b>$ {{ number_format($venta->total, 2) }}</b></p>
        </div>
    </section>

</body>
</html>
