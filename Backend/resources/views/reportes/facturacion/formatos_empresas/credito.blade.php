<!DOCTYPE html>
<html>
<head>
    {{-- <script language="javascript">setTimeout("self.close();",500)</script> --}}
    <title>{{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 14px; margin: 0; padding: 0;}
        html, body{
        }

        #factura{
            /* Media carta*/
            width: 13.97cm; height: 21.59cm;
            font-family: serif;
/*            border: 1px solid red;*/
            width: 13.97cm; height: 21.59cm;
        }

        #header > *, #totales > *{
            position: absolute;
            margin: 0px;
        }

        #cliente        {top: 4cm; left: 2.5cm; width: 10cm; overflow: hidden; white-space: nowrap; text-overflow: ellipsis;}
        #direccion      {top: 4.5cm; left: 2.7cm; width: 10cm; overflow: hidden; white-space: nowrap; text-overflow: ellipsis;}
        #departamento   {top: 5cm; left: 3.3cm; width: 5cm;}
        #fecha          {top: 4cm; left: 14.5cm; }
/*        #municipio      {top: 6.5cm; left: 2.2cm; width: 5cm;}*/
        #nrc            {top: 4.5cm; left: 14.5cm; width: 6cm;}
        #giro            {top: 5cm; left: 14.5cm; width: 6cm;}
        #nit            {top: 5.5cm; left: 14.5cm; width: 6cm;}
        #condicion      {top: 6cm; left: 16.5cm; }


        table   {width: 100px; position: absolute; top: 7.5cm; left: 0.6cm; text-align: left; border-collapse: collapse; }
        table td{height: 0.6cm; text-align: left;}

        .cantidad{ width: 1cm; text-align: center;}
        .producto{ width: 12cm; text-align: left;}
        .precio{ width: 2cm; text-align: center;}
        .sujetas{ width: 1.5cm; text-align: center;}
        .exentas{ width: 1.5cm; text-align: center;}
        .gravadas{ width: 2cm; text-align: right;}


        #letras     {top: 12cm; left: 2cm; width: 11cm; word-break: break-all; white-space: normal;}

        #suma       {top: 12cm; left: 19cm; width: 2cm; text-align: right;}
        #iva        {top: 12.5cm; left: 19cm; width: 2cm; text-align: right;}
        #sub_total  {top: 13cm; left: 19cm; width: 2cm; text-align: right;}
        #iva_retenido  {top: 13.5cm; left: 19cm; width: 2cm; text-align: right;}
        #no_sujeta  {top: 14cm; left: 19cm; width: 2cm; text-align: right;}
        #exenta     {top: 14.5cm; left: 19cm; width: 2cm; text-align: right;}
        #total      {top: 15cm; left: 19cm; width: 2cm; text-align: right;}

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
            <p id="direccion">{{ $cliente->empresa_direccion ? $cliente->empresa_direccion : $cliente->direccion }}</p>


{{--            <p id="municipio">{{ $cliente->municipio }}</p>--}}
            <p id="departamento">{{ $cliente->departamento }}</p>  
            <p id="nit">{{ $cliente->nit }}</p>
            <p id="nrc">{{ $cliente->ncr }}</p>
            <p id="giro">{{ \Illuminate\Support\Str::limit($cliente->giro, 20, $end = '...') }}</p>
            <p id="condicion">
                @if ($venta->estado == 'Pendiente')
                    Credito
                @else
                    Contado
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
            <p id="sub_total"> $ {{ number_format($venta->total + $venta->iva_retenido, 2) }}</p>
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
