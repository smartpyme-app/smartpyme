<!DOCTYPE html>
<html>
<head>
    {{-- <script language="javascript">setTimeout("self.close();",500)</script> --}}
    <title>Emerson {{$venta[0]->documento}} - {{$venta[0]->correlativo}}</title>
    <style>

        *{ font-size: 14px; margin: 0; padding: 0;}
        html, body{
            width: 13.5cm; height: 21.5cm;
            font-family: serif;
/*            border: 1px solid red;*/
        }

        #factura{
            margin-left: 3cm;
            margin-top: -1cm;
            position: relative;
            width: 100%; height: 100%;
        }

        #header > *, #totales > *{
            position: absolute;
            margin: 0px;
        }

        #fecha          {top: 3.6cm; left: 8.5cm; }
        #cliente        {top: 4.2cm; left: 2cm; width: 9cm;}
        #direccion      {top: 4.6cm; left: 2cm; width: 9cm;}
        #municipio      {top: 5.4cm; left: 2cm; width: 5cm;}
        #departamento   {top: 4.6cm; left: 8.5cm; width: 5cm;}
        #condicion      {top: 6cm; left: 10.3cm; }
        #nit            {top: 6cm; left: 2cm; }


        table   {position: absolute; top: 7.9cm; left: 0.6cm; text-align: left; border-collapse: collapse; }
        table td{height: 0.6cm; text-align: left;}

        .cantidad{ width: 1cm; text-align: center;}
        .producto{ width: 5.3cm; text-align: left;}
        .precio{ width: 1.5cm; text-align: center;}
        .sujetas{ width: 0.9cm; text-align: center;}
        .exentas{ width: 0.9cm; text-align: center;}
        .gravadas{ width: 1.8cm; text-align: right;}
        

        #letras     {top: 17.5cm; left: 2cm; width: 5cm; word-break: break-all; white-space: normal;}
        #correlativo{top: 18cm; left: 2cm;; width: 9cm;;}
        #info       {top: 9.2cm; left: 3cm; width: 9cm;;}

        #suma       {top: 17.7cm; left: 10cm; width: 2cm; text-align: right;}
        #no_sujeta  {top: 19cm; left: 10cm; width: 2cm; text-align: right;}
        #exenta     {top: 19.7cm; left: 10cm; width: 2cm; text-align: right;}
        #total      {top: 20.2cm; left: 10cm; width: 2cm; text-align: right;}

        .no-print{position: absolute;}

    </style>
    
    <style media="print"> .no-print{display: none; } </style>

</head>
<body>
<body>

    <section id="factura">
        <div id="header">
            <p id="fecha">{{ \Carbon\Carbon::parse($venta[0]->fecha)->format('d/m/Y') }}</p>
            <p id="cliente">{{ $venta[0]->cliente }}</p>
            <p id="direccion">{{ $cliente->direccion }}</p>
            <p id="municipio">{{ $cliente->municipio }}</p>
            <p id="departamento">{{ $cliente->departamento }}</p>
            <p id="condicion">
                @if ($venta[0]->estado == 'Pagada')
                    X
                @else
                    <span style="margin-left: 1.6cm;">X</span>
                @endif
            </p>
            <p id="nit">{{ $cliente->nit }}</p>
        </div>
                    
        <table>
            @php($iva = $venta[0]->empresa()->iva / 100);
            @foreach($venta[0]->detalles as $detalle)
            <tr>
                <td class="cantidad">   {{ number_format($detalle->cantidad, 0) }}</td>
                <td class="producto">   {{ $detalle->producto  }}</td>
                <td class="precio">     ${{ number_format($detalle->precio + (($venta[0]->iva != 0) ? ($detalle->precio * $iva) : 0), 2) }}</td>
                <td class="sujetas">   </td>
                <td class="exentas">    </td>
                <td class="gravadas">  ${{ number_format($detalle->total + (($venta[0]->iva != 0)  ? ($detalle->total * $iva) : 0), 2) }} </th>
            </tr>
            @endforeach
        </table>

        <div id="totales">
            <p id="letras"> {{$dolares}} DÓLARES CON {{$centavos}} CENTAVOS.</p>
            {{-- <p id="correlativo">{{ $venta[0]->correlativo }}</p> --}}

            <p id="suma"> $ {{ number_format($venta[0]->total_venta, 2) }}</p>
            {{-- <p id="iva"> $ {{ number_format($venta[0]->iva, 2) }}</p> --}}
            <p id="total"> <b>$ {{ number_format($venta[0]->total_venta, 2) }}</b></p>
        </div>
    </section>

</body>
</html>
