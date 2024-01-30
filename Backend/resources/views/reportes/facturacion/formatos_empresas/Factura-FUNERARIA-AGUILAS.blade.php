<!DOCTYPE html>
<html>
<head>
    {{-- <script language="javascript">setTimeout("self.close();",500)</script> --}}
    <title>FUNERARIA AGUILAS {{$venta->documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 14px; margin: 0; padding: 0;}
        html, body{
            width: 13.5cm; height: 21.5cm;
            font-family: serif;
/*            border: 1px solid red;*/
        }

        #factura{
            margin-left: 0cm;
            margin-top: 0cm;
            position: relative;
            width: 100%; height: 100%;
        }

        #header > *, #totales > *{
            position: absolute;
            margin: 0px;
        }

        #fecha          {top: 4.7cm; left: 2cm; }
        #cliente        {top: 5.2cm; left: 2.5cm; width: 9cm;}
        #direccion      {top: 5.7cm; left: 2.5cm; width: 9cm;}
        #condicion      {top: 6.2cm; left: 10.3cm; }
        #nit            {top: 4.2cm; left: 10cm; }


        table   {position: absolute; top: 7.7cm; left: 1cm; text-align: left; border-collapse: collapse; }
        table td{height: 0.6cm; text-align: left;}

        .cantidad{ width: 1.2cm; text-align: center;}
        .producto{ width: 6cm; text-align: left;}
        .precio{ width: 1cm; text-align: center;}
        .sujetas{ width: 0.9cm; text-align: center;}
        .exentas{ width: 0.9cm; text-align: center;}
        .gravadas{ width: 1.5cm; text-align: right;}
        

        #letras     {top: 17cm; left: 2cm; width: 5cm; word-break: break-all; white-space: normal;}
        #correlativo{top: 17.5cm; left: 2cm;; width: 9cm;;}

        #suma       {top: 17cm; left: 10.8cm; width: 2cm; text-align: right;}
        #no_sujeta  {top: 17.5cm; left: 10.8cm; width: 2cm; text-align: right;}
        #exenta     {top: 18cm; left: 10.8cm; width: 2cm; text-align: right;}
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
            <p id="cliente">{{ $venta->cliente }}</p>
            <p id="direccion">{{ $cliente->direccion }} {{ $cliente->municipio }} {{ $cliente->departamento }}</p>
            {{-- <p id="condicion">
                @if ($venta->estado == 'Pagada')
                    X
                @else
                    <span style="margin-left: 1.6cm;">X</span>
                @endif
            </p> --}}
            <p id="nit">{{ $cliente->dui }}</p>
        </div>
                    
        <table>
            @php($iva = $venta->empresa()->pluck('iva')->first() / 100)
            @foreach($venta->detalles as $detalle)
            <tr>
                <td class="cantidad">   {{ number_format($detalle->cantidad, 0) }}</td>
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
