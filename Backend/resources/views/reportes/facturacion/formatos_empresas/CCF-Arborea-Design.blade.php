<!DOCTYPE html>
<html>
<head>
    {{-- <script language="javascript">setTimeout("self.close();",500)</script> --}}
    <title>Arborea Design {{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 13px; margin: 0; padding: 0;}
        html, body{
            width: 19.5cm; height: 20cm;
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

        #fecha          {top: 4.3cm; left: 16cm; }
        #cliente        {top: 4.3cm; left: 4cm;}
        #direccion      {top: 4.8cm; left: 4.5cm;}
        #municipio      {top: 4cm; left: 4.5cm;}
        #departamento   {top: 4.5cm; left: 4.5cm; }
        #nrc            {top: 3.5cm; left: 15cm; }
        #nit            {top: 4cm; left: 15cm; }
        #giro            {top: 4.5cm; left: 15cm;}
        #condicion      {top: 5.5cm; left: 17.5cm; }


        table   {position: absolute; top: 10.5cm; left: 2.5cm; text-align: left; border-collapse: collapse; }
        table td{height: 0.5cm; text-align: left;}

        .cantidad{ width: 1cm; text-align: center;}
        .producto{ width: 9.5cm; text-align: left;}
        .precio{ width: 2.5cm; text-align: center;}
        .sujetas{ width: 1.2cm; text-align: center;}
        .exentas{ width: 1.2cm; text-align: center;}
        .gravadas{ width: 1.5cm; text-align: right;}


        #letras     {top: 22.5cm; left: 3.5cm; width: 7cm; word-break: break-all; white-space: normal;}
        #correlativo{top: 13cm; left: 2cm;; width: 9cm;;}

        #suma       {top: 22cm; left: 18cm; width: 1.5cm; text-align: right;}
        #iva        {top: 22.5cm; left: 18cm; width: 1.5cm; text-align: right;}
        #sub_total  {top: 23cm; left: 18cm; width: 1.5cm; text-align: right;}
        #iva_retenido  {top: 22.9cm; left: 18cm; width: 1.5cm; text-align: right;}
        #no_sujeta  {top: 23.2cm; left: 18cm; width: 1.5cm; text-align: right;}
        #exenta     {top: 23.5cm; left: 18cm; width: 1.5cm; text-align: right;}
        #cuenta_a_terceros {top: 24.5cm; left: 18cm; width: 1.5cm; text-align: right;}
        #total      {top: 26cm; left: 18cm; width: 1.5cm; text-align: right;}

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
        <p id="direccion">{{ $cliente->direccion }}</p>
        <p id="municipio">{{ $cliente->municipio }}</p>
        <p id="departamento">{{ $cliente->departamento }}</p>
        <p id="nit">{{ $cliente->nit }}</p>
        <p id="nrc">{{ $cliente->ncr }}</p>
        <p id="giro">{{ \Illuminate\Support\Str::limit($cliente->giro, 20, $end = '...') }}</p>
        <p id="condicion">
            @if ($venta->estado == 'Pagada')
                X
            @else
                <span style="margin-left: 1.6cm;">X</span>
            @endif
        </p>
        <p id="nit">{{ $cliente->nit }}</p>
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

        <p id="suma"> $ {{ number_format($venta->sub_total, 2) }}</p>
        <p id="iva"> $ {{ number_format($venta->iva, 2) }}</p>
        <p id="sub_total"> $ {{ number_format($venta->total, 2) }}</p>
        @if($venta->iva_retenido > 0)
            <p id="iva_retenido"> $ {{ number_format($venta->iva_retenido, 2) }}</p>
        @endif
        @if($venta->no_sujeta > 0)
            <p id="no_sujeta"> $ {{ number_format($venta->no_sujeta, 2) }}</p>
        @endif
        @if($venta->exenta > 0)
            <p id="exenta"> $ {{ number_format($venta->exenta, 2) }}</p>
        @endif
        @if($venta->cuenta_a_terceros > 0)
            <p id="cuenta_a_terceros"> $ {{ number_format($venta->cuenta_a_terceros, 2) }}</p>
        @endif
        <p id="total"> <b>$ {{ number_format($venta->total, 2) }}</b></p>
    </div>
</section>

</body>
</html>
