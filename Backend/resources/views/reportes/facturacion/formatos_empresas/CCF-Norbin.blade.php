<!DOCTYPE html>
<html>
<head>
    {{-- <script language="javascript">setTimeout("self.close();",500)</script> --}}
    <title>Norbin {{$venta->nombre_documento}} - {{$venta->correlativo}}</title>
    <style>

        *{ font-size: 15px; margin: 0; padding: 0;}
        html, body{
            width: 22cm; height: 29cm;
            font-family: serif;
            /*border: 1px solid red;*/
        }

        #factura{
            margin-left: 0cm;
            margin-top: 0cm;
            /*position: relative;*/
        }

        #header > *, #totales > *{
            position: absolute;
            margin: 0px;
        }

        #fecha          {top: 6cm; left: 17.5cm;}
        #cliente        {top: 6cm; left: 4.4cm;}
        #direccion      {top: 6.5cm; left: 4.5cm;}
        #municipio      {top: 7.6cm; left: 4cm;}
        #departamento   {top: 7.6cm; left: 13.5cm;}
        #nrc            {top: 8.5cm; left: 3cm;}
        #nit            {top: 8.5cm; left: 6.5cm; }
        #giro            {top: 8.5cm; left: 12cm;}
        #condicion      {top: 5.5cm; left: 17cm; }


        table   {position: absolute; top: 12cm; left: 0.5cm; text-align: left; border-collapse: collapse; }
        table td{height: 0.5cm; text-align: left;}

        .cantidad{ width: 3cm; text-align: center;}
        .producto{ width: 11cm; text-align: left;}
        .precio{ width: 1.5cm; text-align: center;}
        .sujetas{ width: 1.5cm; text-align: center;}
        .exentas{ width: 1.5cm; text-align: center;}
        .gravadas{ width: 1.5cm; text-align: right;}


        #letras     {top: 23.5cm; left: 4cm; width: 9cm; word-break: break-all; white-space: normal;}
        #correlativo{top: 13cm; left: 2cm; width: 9cm;}

        #suma       {top: 23.5cm; left: 18cm; width: 1.5cm; text-align: right;}
        #iva        {top: 23.9cm; left: 18cm; width: 1.5cm; text-align: right;}
        #sub_total  {top: 24.3cm; left: 18cm; width: 1.5cm; text-align: right;}
        #iva_retenido  {top: 24.7cm; left: 18cm; width: 1.5cm; text-align: right;}
        #no_sujeta  {top: 25.1cm; left: 18cm; width: 1.5cm; text-align: right;}
        #exenta     {top: 25.5cm; left: 18cm; width: 1.5cm; text-align: right;}
        #cuenta_a_terceros {top: 17cm; left: 18cm; width: 1.5cm; text-align: right;}
        #total      {top: 26.5cm; left: 18cm; width: 1.5cm; text-align: right;}

        .no-print{position: absolute;}

    </style>

    <style media="print"> .no-print{display: none; } </style>

</head>
<body>

{{--<section id="factura" style="border:1px solid #ffffff00;background-image: url('C:\Users\josep\Documents\smartpyme\smartpyme\Backend\public\img\CCF-norbin.jpg'); background-repeat: no-repeat; background-size: 100% 100%; height: 29cm; width: 22cm;">--}}
{{--<section id="factura">--}}
    <div id="header">
        <p id="fecha">{{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
        <p id="cliente">{{ $venta->nombre_cliente }}</p>
        <p id="direccion">{{ $cliente->direccion }}</p>
        <p id="municipio">{{ $cliente->municipio }}</p>
        <p id="departamento">{{ $cliente->departamento }}</p>
        <p id="nit">{{ $cliente->nit }}</p>
        <p id="nrc">{{ $cliente->ncr }}</p>
        <p id="giro">{{ \Illuminate\Support\Str::limit($cliente->giro, 20, $end = '...') }}</p>
{{--        <p id="condicion">--}}
{{--            @if ($venta->estado == 'Pagada')--}}
{{--                X--}}
{{--            @else--}}
{{--                <span style="margin-left: 1.6cm;">X</span>--}}
{{--            @endif--}}
{{--        </p>--}}
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
