<!DOCTYPE html>
<html>
<head>
    {{-- <script language="javascript">setTimeout("self.close();",500)</script> --}}
    <title>Clínica {{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 14px; margin: 0; padding: 0;}
        html, body{
            font-family: serif;
        }

        #factura{
            width: 13.6cm; height: 21.2cm;
            margin-left: 0cm;
            margin-top: 0cm;
            position: relative;
        }

        #header > *, #totales > *{
            position: absolute;
            margin: 0px;
        }

        #fecha          {top: 4cm; left: 10cm; }

        #cliente        {top: 4.5cm; left: 5.5cm; width: 9cm;}
        #direccion      {top: 5cm; left: 2.5cm; width: 9cm;}
        #nit            {top: 6cm; left: 5.5cm; }


        table   {position: absolute; top: 8cm; left: 0.5cm; text-align: left; border-collapse: collapse; width: 12cm;}
        table td{height: 0.6cm; text-align: left;}

        .cantidad{ width: 1.5cm; text-align: center;}
        .producto{ width: 6cm; text-align: left;}
        .precio{ width: 2cm; text-align: center;}
        .gravadas{ width: 2cm; text-align: right;}
        

        #letras     {top: 18cm; left: 1cm; width: 6cm; word-break: break-all; white-space: normal;}
        #correlativo{top: 17.5cm; left: 2cm;; width: 9cm;;}

        #suma       {top: 18cm; left: 10.5cm; width: 2cm; text-align: right;}
        #renta      {top: 18.7cm; left: 10.5cm; width: 2cm; text-align: right;}
        #total      {top: 19.4cm; left: 10.5cm; width: 2cm; text-align: right;}

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
                <p id="direccion">{{ $cliente->direccion }} {{ $cliente->municipio }} {{ $cliente->departamento }}</p>
                <p id="nit">{{ $cliente->dui }}</p>
            @endif
        </div>
                    
        <table>
            @php($iva = $venta->empresa()->pluck('iva')->first() / 100)
            @foreach($venta->detalles as $detalle)
            <tr>
                <td class="cantidad">   {{ number_format($detalle->cantidad, 0) }}</td>
                <td class="producto">   {{ $detalle->nombre_producto  }}</td>
                <td class="precio">     ${{ number_format($detalle->precio + (($venta->iva != 0) ? ($detalle->precio * $iva) : 0), 2) }}</td>
                <td class="gravadas">  ${{ number_format($detalle->total + (($venta->iva != 0)  ? ($detalle->total * $iva) : 0), 2) }} </th>
            </tr>
            @endforeach
        </table>

        <div id="totales">
            <p id="letras"> {{$dolares}} DÓLARES CON {{$centavos}} CENTAVOS.</p>
            {{-- <p id="correlativo">{{ $venta->correlativo }}</p> --}}

            <p id="suma"> $ {{ number_format($venta->total, 2) }}</p>
            <p id="renta"> $ {{ number_format($venta->renta_retenida, 2) }}</p>
            <p id="total"> <b>$ {{ number_format($venta->total, 2) }}</b></p>
        </div>
    </section>

</body>
</html>
