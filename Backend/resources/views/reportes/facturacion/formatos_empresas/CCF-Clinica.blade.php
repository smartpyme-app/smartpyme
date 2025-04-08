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
            margin-left: 0cm;
            margin-top: 0cm;
            position: relative;
            width: 13.7cm; height: 21.4cm;
        }

        #header > *, #totales > *{
            position: absolute;
            margin: 0px;
        }

        #fecha          {top: 4.4cm; left: 10cm; }
        
        #cliente        {top: 5cm; left: 2.2cm; width: 9cm;}
        #direccion      {top: 5.5cm; left: 2.2cm; width: 9cm;}
        #departamento   {top: 5.5cm; left: 10cm; width: 4cm;}
        #giro           {top: 6cm; left: 2.5cm; }
        #condicion      {top: 6.5cm; left: 4.5cm; }
        #nrc            {top: 6cm; left: 10.5cm; }
        #nit            {top: 6.5cm; left: 9.5cm; }


        table   {position: absolute; top: 8cm; left: 0.5cm; text-align: left; border-collapse: collapse; width:12.1cm; }
        table td{height: 0.6cm; text-align: left;}

        .cantidad{ width: 1cm; text-align: center;}
        .producto{ width: 5.7cm; text-align: left;}
        .precio{ width: 1.5cm; text-align: center;}
        .sujetas{ width: 1cm; text-align: center;}
        .exentas{ width: 1cm; text-align: center;}
        .gravadas{ width: 2cm; text-align: right;}
        

        #letras     {top: 16.5cm; left: 1cm; width: 6cm; word-break: break-all; white-space: normal;}

        #suma       {top: 16.5cm; left: 10.6cm; width: 2cm; text-align: right;}
        #iva        {top: 17cm; left: 10.6cm; width: 2cm; text-align: right;}
        #sub_total  {top: 17.5cm; left: 10.6cm; width: 2cm; text-align: right;}
        #ivaretenido{top: 18cm; left: 10.6cm; width: 2cm; text-align: right;}
        #no_sujeta  {top: 18.5cm; left: 10.6cm; width: 2cm; text-align: right;}
        #exenta     {top: 19cm; left: 10.6cm; width: 2cm; text-align: right;}
        #total      {top: 19.5cm; left: 10.6cm; width: 2cm; text-align: right;}

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
            <p id="direccion">{{ $cliente->municipio }} {{ $cliente->direccion }}</p>
            <p id="departamento">{{ $cliente->departamento }} </p>
            <p id="nit">{{ $cliente->nit }}</p>
            <p id="nrc">{{ $cliente->ncr }}</p>
            <p id="giro">{{ \Illuminate\Support\Str::limit($cliente->giro, 40, $end = '...') }}</p>
            <p id="condicion">
                @if ($venta->estado == 'Pagada')
                    Contado
                @else
                    <span style="margin-left: 1.6cm;">{{$venta->estado}}</span>
                @endif
            </p>
        </div>
                    
        <table>
           @php($iva = $venta->empresa()->pluck('iva')->first() / 100)
            @foreach($venta->detalles as $detalle)
            <tr>
                <td class="cantidad">   {{ number_format($detalle->cantidad, 0) }}</td>
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
            <p id="sub_total"> $ {{ number_format($venta->total, 2) }}</p>
            <p id="total"> <b>$ {{ number_format($venta->total, 2) }}</b></p>
        </div>
    </section>

</body>
</html>
