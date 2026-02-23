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

        #fecha          {top: 3.5cm; left: 12cm; }
        #cliente        {top: 4.5cm; left: 2.2cm; width: 9cm;}
        #direccion      {top: 5cm; left: 2.2cm; width: 9cm;}
/*        #municipio      {top: 6.5cm; left: 2.2cm; width: 5cm;}*/
/*        #departamento   {top: 7cm; left: 3cm; width: 5cm;}*/
        #nit            {top: 5.5cm; left: 9cm; }
/*        #nrc            {top: 6.5cm; left: 10cm; }*/
/*        #giro            {top: 7.5cm; left: 9cm; }*/
/*        #condicion      {top: 8cm; left: 10cm; }*/


        table   {position: absolute; top: 7cm; left: 0.6cm; text-align: left; border-collapse: collapse; }
        table td{height: 0.6cm; text-align: left;}

        .cantidad{ width: 1cm; text-align: center;}
        .producto{ width: 7cm; text-align: left;}
        .precio{ width: 1.8cm; text-align: center;}
        .sujetas{ width: 1.5cm; text-align: center;}
        .exentas{ width: 1.5cm; text-align: center;}
        .gravadas{ width: 1.8cm; text-align: right;}


        #letras     {top: 17cm; left: 2cm; width: 5cm; word-break: break-all; white-space: normal;}

        #suma       {top: 17cm; left: 14cm; width: 2cm; text-align: right;}
/*        #iva        {top: 16.2cm; left: 14cm; width: 2cm; text-align: right;}*/
        #exenta     {top: 17.5cm; left: 14cm; width: 2cm; text-align: right;}
        #no_sujeta  {top: 18cm; left: 14cm; width: 2cm; text-align: right;}
        #sub_total  {top: 18.5cm; left: 14cm; width: 2cm; text-align: right;}
        #iva_retenido{top: 19cm; left: 14cm; width: 2cm; text-align: right;}
        #propina    {top: 19.5cm; left: 14cm; width: 2cm; text-align: right;}
        #total      {top: 20cm; left: 14cm; width: 2cm; text-align: right;}
        #total_con_propina {top: 20.5cm; left: 14cm; width: 2cm; text-align: right;}

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
                @if($venta->cliente->direccion == null)
                    <p id="direccion">{{ $cliente->empresa_direccion }} {{ $cliente->municipio }} {{ $cliente->departamento }}</p>
                @else
                    <p id="direccion">{{ $cliente->direccion }} {{ $cliente->municipio }} {{ $cliente->departamento }}</p>
                @endif
            <p id="nit">{{ $cliente->dui ? $cliente->dui : $cliente->nit }}</p>
            @endif
            {{-- <p id="municipio">{{ $cliente->municipio }}</p> --}}
            {{-- <p id="departamento">{{ $cliente->departamento }}</p> --}}
            {{-- <p id="nit">{{ $cliente->nit }}</p> --}}
            {{-- <p id="nrc">{{ $cliente->ncr }}</p> --}}
            {{-- <p id="giro">{{ \Illuminate\Support\Str::limit($cliente->giro, 20, $end = '...') }}</p> --}}
            {{-- <p id="condicion">
                @if ($venta->estado == 'Pagada')
                    X
                @else
                    <span style="margin-left: 1.6cm;">X</span>
                @endif
            </p> --}}
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
            <p id="suma"> $ {{ number_format($venta->total, 2) }}</p>
            {{-- <p id="iva"> $ {{ number_format($venta->iva, 2) }}</p> --}}
            <p id="sub_total"> $ {{ number_format($venta->total, 2) }}</p>
            <p id="no_sujeta"> $ {{ number_format($venta->no_sujeta, 2) }}</p>
            <p id="exenta"> $ {{ number_format($venta->exenta, 2) }}</p>
            <p id="iva_retenido"> $ {{ number_format($venta->iva_retenido, 2) }}</p>
            @php
                $propina = floatval($venta->propina ?? 0);
            @endphp
            @if($propina > 0)
                <p id="propina">Propina: $ {{ number_format($propina, 2) }}</p>
                <p id="total"> <b>Total: $ {{ number_format($venta->total, 2) }}</b></p>
                <p id="total_con_propina"> <b>Total + Propina: $ {{ number_format($venta->total + $propina, 2) }}</b></p>
            @else
                <p id="total"> <b>$ {{ number_format($venta->total, 2) }}</b></p>
            @endif
        </div>
    </section>

</body>
</html>
