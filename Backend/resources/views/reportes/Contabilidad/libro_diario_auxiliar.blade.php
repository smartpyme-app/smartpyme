<!DOCTYPE html>
<html>
<head>
    {{-- <script language="javascript">setTimeout("self.close();",500)</script> --}}
    <title>Libro diario auxiliar </title>
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

        #fecha          {top: 4cm; left: 16cm; }
        #cliente        {top: 4cm; left: 4cm;}
        #direccion      {top: 4.5cm; left: 4.5cm;}
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
        #iva        {top: 22.3cm; left: 18cm; width: 1.5cm; text-align: right;}
        #sub_total  {top: 22.6cm; left: 18cm; width: 1.5cm; text-align: right;}
        #iva_retenido  {top: 22.9cm; left: 18cm; width: 1.5cm; text-align: right;}
        #no_sujeta  {top: 23.2cm; left: 18cm; width: 1.5cm; text-align: right;}
        #exenta     {top: 23.5cm; left: 18cm; width: 1.5cm; text-align: right;}
        #cuenta_a_terceros {top: 24.5cm; left: 18cm; width: 1.5cm; text-align: right;}
        #total      {top: 16cm; left: 18cm; width: 1.5cm; text-align: right;}

        .no-print{position: absolute;}

    </style>

    <style media="print"> .no-print{display: none; } </style>

</head>
<body>
<body>

<section id="factura">
    <div id="header">
{{--        <p id="fecha">{{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>--}}
{{--        <p id="cliente">{{ $venta->nombre_cliente }}</p>--}}
{{--        <p id="direccion">{{ $cliente->direccion }}</p>--}}
{{--        <p id="municipio">{{ $cliente->municipio }}</p>--}}
{{--        <p id="departamento">{{ $cliente->departamento }}</p>--}}
{{--        <p id="nit">{{ $cliente->nit }}</p>--}}
{{--        <p id="nrc">{{ $cliente->ncr }}</p>--}}
{{--        <p id="giro">{{ \Illuminate\Support\Str::limit($cliente->giro, 20, $end = '...') }}</p>--}}


{{--        a la derecha del documento --}}
        <p id="logo">{{$empresa->logo}}</p>

{{--        al centro del documento --}}
        <p id="empresa_nombre">{{$empresa->nombre}}</p>
        <p id="titulo_doc">Movimiento de una cuenta</p>
        <p id="fechas_filtro">Desde: {{$desde}} Hasta: {{$hasta}}</p>

{{--        a la izquierda del documento--}}
        <p id="fecha_actual">4/05/2024</p>
        <p id="hora_reporte">06:15:18 a.m.</p>

    </div>

    <table>
        {{--        @foreach($venta->detalles as $detalle)    for each de cada cuenta relacionada con la empresa    --}}

{{--            titulo de la cuenta--}}
{{--            <tr>Cuenta: {{ $num_cuenta }} - {{$nom_cuenta}}</tr>--}}

            <tr>
                <th>Partida</th>
                <th>Fecha</th>
                <th>Concepto</th>
                <th>Cargo</th>
                <th>Abono</th>
                <th>Saldo</th>
            </tr>
            <tr>
                <td class="id_partida">         </td>
                <td class="fecha_partida">      </td>
                <td class="concepto">           </td>
                <td class="cargo">              </td>
                <td class="abono">              </td>
                <td class="saldo">              </td>
            </tr>
{{--        @endforeach--}}
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
