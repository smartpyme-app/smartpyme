<!DOCTYPE html>
<html>
<head>
    {{-- <script language="javascript">setTimeout("self.close();",500)</script> --}}
    <title>TecnoGadget {{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 12px; margin: 0; padding: 0;}
        html, body{
            width: 11cm; height: 21.5cm;
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

        #fecha          {top: 4cm; left: 6cm; }
        #cliente        {top: 4.5cm; left: 0.3cm; width: 8cm; white-space:nowrap;}
        #direccion      {top: 5cm; left: 0.3cm; width: 8cm; white-space:nowrap;}
        #condicion      {top: 5.5cm; left: 5.7cm; }
        #dui            {top: 5.5cm; left: 3.1cm; }
        #nit            {top: 5.5cm; left: 0.3cm; }
        #nrc            {top: 6cm; left: 0.3cm; }
        #giro           {top: 6cm; left: 3cm; width: 4cm; white-space:nowrap;}


        table   {position: absolute; top: 7cm; left: 0cm; text-align: left; border-collapse: collapse; }
        table td{height: 0.6cm; text-align: left;}

        .cantidad{ width: 1cm; text-align: center;}
        .producto{ width: 3.7cm; text-align: left;}
        .precio{ width: 1cm; text-align: center;}
        .sujetas{ width: 0.8cm; text-align: center;}
        .exentas{ width: 0.8cm; text-align: center;}
        .gravadas{ width: 1cm; text-align: right;}


        #letras     {top: 15cm; left: 0.2cm; width: 5cm; word-break: break-all; white-space: normal;}
        #correlativo{top: 16cm; left: 0.2cm;; width: 9cm;}

        #suma       {top: 14cm; left: 4.3cm; width: 4cm; text-align: right;}
        #iva        {top: 14.5cm; left: 4.3cm; width: 4cm; text-align: right;}
        #sub_total  {top: 15cm; left: 4.3cm; width: 4cm; text-align: right;}
        #no_sujeta  {top: 15.5cm; left: 4.3cm; width: 4cm; text-align: right;}
        #exenta     {top: 16cm; left: 4.3cm; width: 4cm; text-align: right;}
        #total      {top: 16.5cm; left: 4.3cm; width: 4cm; text-align: right;}

        .no-print{position: absolute;}

    </style>

    <style media="print"> .no-print{display: none; } </style>

</head>
<body>
<body>

<section id="factura">
    <div id="header">
        <p id="fecha"><b>Fecha: </b>{{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
        <p id="cliente"><b>Cliente: </b>{{ $venta->nombre_cliente }}</p>
        <p id="direccion"><b>Dirección: </b>{{ $cliente->direccion }} {{ $cliente->municipio }} {{ $cliente->departamento }}</p>
        <p id="nit"><b>NIT: </b>{{ $cliente->nit }}</p>
        <p id="dui"><b>DUI: </b>{{ $cliente->dui }}</p>
        <p id="nrc"><b>NRC: </b>{{ $cliente->ncr }}</p>
        <p id="giro"><b>Giro: </b>{{ \Illuminate\Support\Str::limit($cliente->giro, 20, $end = '...') }}</p>
        <p id="condicion">
            @if ($venta->estado == 'Pagada')
                Contado
            @else
                Crédito
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
        {{-- <p id="correlativo">{{ $venta->correlativo }}</p> --}}

        <p id="suma">SUMA: ${{ number_format($venta->sub_total, 2) }}</p>
        <p id="iva">IVA: ${{ number_format($venta->iva, 2) }}</p>
        <p id="sub_total">SUBTOTAL: ${{ number_format($venta->total, 2) }}</p>
        <p id="total"> <b>TOTAL: ${{ number_format($venta->total, 2) }}</b></p>
    </div>
</section>

</body>
</html>
