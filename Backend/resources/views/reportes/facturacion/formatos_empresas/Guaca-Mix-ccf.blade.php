<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    {{-- <script language="javascript">setTimeout("self.close();",500)</script> --}}
    <title>Guaca Mix {{$venta->documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 16px; margin: 0; padding: 0;}
        html, body {
            width: 20.5cm; height: 23.5cm;
            font-family: serif;
            margin-top: 0cm;
        }

        #factura{
/*            margin-left: 1.4cm;*/
/*            margin-top: 5cm;*/
            width: 18cm; height: 16cm;
            width: 100%; height: 100%;
/*            border: 1px solid red;*/
        }

        #header, #totales{
            position: relative;
        }

        #header > *, #totales > *{
            position: absolute;
            margin: 0px 0px 0px 0px;
/*            overflow: hidden; white-space: pre;*/
        }

        #cliente        {top: 5.5cm; left: 3cm; width: 8cm; height:0.5cm; overflow: hidden;}
        #fecha          {top: 5.5cm; left: 13cm;}
        #direccion      {top: 6cm; left: 3cm;  width: 7.5cm; height:0.5cm; overflow: hidden;}
        #nrc            {top: 6cm; left: 13cm; }
        #departamento   {top: 6.5cm; left: 4cm; }
        #nit            {top: 6.5cm; left: 13cm; }
        #giro           {top: 7cm; left: 12.5cm;  width: 7cm; height:0.5cm; overflow: hidden;}
        #condicion      {top: 7.5cm; left: 14cm;}


        table   {position: absolute; top: 9cm; left: 1.4cm; text-align: left; border-collapse: collapse; }
        table td{height: 0.5cm;}

        .cantidad{ width: 1.3cm; text-align: center;}
        .producto{ width: 9.6cm;}
        .precio{ width: 1.8cm; text-align: center;}
        .sujetas{ width: 1.7cm; text-align: center;}
        .exentas{ width: 1.7cm; text-align: center;}
        .gravadas{ width: 1.7cm; text-align: right;}
        

        #letras     {top: 17.6cm; left: 1.5cm; width: 4.2cm; word-break: break-all; white-space: normal;}
        #correlativo{top: 18.2cm; left: 2cm;; width: 5cm;}

        #suma       {top: 17.3cm; left: 17.5cm; width: 1.7cm; text-align: right;}
        #iva        {top: 17.8cm; left: 17.5cm; width: 1.7cm; text-align: right;}
        #subtotal   {top: 18.3cm; left: 17.5cm; width: 1.7cm; text-align: right;}
        #iva_retenido{top: 18.8cm; left: 17.5cm; width: 1.7cm; text-align: right;}
        #no_sujeta  {top: 19.3cm; left: 17.5cm; width: 1.7cm; text-align: right;}
        #exenta     {top: 19.8cm; left: 17.5cm; width: 1.7cm; text-align: right;}
        #total      {top: 20.3cm; left: 17.5cm; width: 1.7cm; text-align: right;}

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
            <p id="nit">{{ $cliente->nit ? $cliente->nit : $cliente->dui  }}</p>
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
            <p id="subtotal"> $ {{ number_format($venta->total, 2) }}</p>
            {{-- <p id="iva_retenido"> $ {{ number_format($venta->iva_retenido, 2) }}</p> --}}
            {{-- <p id="no_sujeta"> $ {{ number_format($venta->no_sujeta, 2) }}</p> --}}
            {{-- <p id="exenta"> $ {{ number_format($venta->exenta, 2) }}</p> --}}
            <p id="total"> <b>$ {{ number_format($venta->total, 2) }}</b></p>
        </div>
    </section>

</body>
</html>

