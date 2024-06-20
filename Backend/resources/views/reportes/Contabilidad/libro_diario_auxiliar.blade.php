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

        #logo          {top: 0.5cm; left: 0.5cm }
        #empresa_nombre  { top: 0.5cm; left: 12.5cm;}
        #titulo_doc      {top: 1.5cm; left: 12cm;}
        #fechas_filtro  {top: 2.5cm; left: 11cm;}
        #fecha_actual   {top: 0.5cm; left: 20cm; }
        #hora_reporte    {top: 1.5cm; left: 20cm; }
        #nit            {top: 4cm; left: 15cm; }
        #giro            {top: 4.5cm; left: 15cm;}
        #condicion      {top: 5.5cm; left: 17.5cm; }


        table   {position: absolute; top: 4.5cm; left: 2.5cm; text-align: left; border-collapse: collapse; }
        table td{height: 0.5cm; text-align: left;}

        .id_partida{ width: 1.5cm; text-align: center;}
        .fecha_partida{ width: 5cm; text-align: center;}
        .concepto{ width: 7cm; text-align: left;}
        .cargo{ width: 3cm; text-align: center;}
        .abono{ width: 3cm; text-align: center;}
        .saldo{ width: 3cm; text-align: center;}


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

        /*para el brake page */

        .page-break {
            page-break-before: always;
        }

        .invoice-articles-table {
            padding-bottom: 50px; //height of your footer
        }

    </style>

    <style media="print"> .no-print{display: none; } </style>

</head>
<body>
<body>

<section id="factura">
    <div id="header">

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

    <div style="page-break-after:auto;">
    <table>

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
            @foreach($detalles as $detalle_par)
            <tr>
                <td class="id_partida">     {{$detalle_par->id_partida}}    </td>
                <td class="fecha_partida"> {{$detalle_par->created_at}}    </td>
                <td class="concepto">        {{$detalle_par->concepto}}    </td>
                <td class="cargo">            {{$detalle_par->cargo}}   </td>
                <td class="abono">           {{$detalle_par->abono}}    </td>
                <td class="saldo">            {{$detalle_par->saldo}}   </td>
            </tr>
            @if($loop->iteration == 30 or $loop->iteration == 60 or $loop->iteration == 90 )
            </table>
                <div class="page-break"></div>
            <table class="table invoice-articles-table">
            <thead>
            <tr>
                <th>#</th>
                ...
            </thead>

            @endif
            @endforeach
    </table>

{{--    <div id="totales">--}}
{{--        <p id="letras"> {{$dolares}} DÓLARES CON {{$centavos}} CENTAVOS.</p>--}}
{{--        --}}{{-- <p id="correlativo">{{ $venta->correlativo }}</p> --}}

{{--        <p id="suma"> $ {{ number_format($venta->sub_total, 2) }}</p>--}}
{{--        <p id="iva"> $ {{ number_format($venta->iva, 2) }}</p>--}}
{{--        <p id="sub_total"> $ {{ number_format($venta->total, 2) }}</p>--}}
{{--        @if($venta->iva_retenido > 0)--}}
{{--            <p id="iva_retenido"> $ {{ number_format($venta->iva_retenido, 2) }}</p>--}}
{{--        @endif--}}
{{--        @if($venta->no_sujeta > 0)--}}
{{--            <p id="no_sujeta"> $ {{ number_format($venta->no_sujeta, 2) }}</p>--}}
{{--        @endif--}}
{{--        @if($venta->exenta > 0)--}}
{{--            <p id="exenta"> $ {{ number_format($venta->exenta, 2) }}</p>--}}
{{--        @endif--}}
{{--        @if($venta->cuenta_a_terceros > 0)--}}
{{--            <p id="cuenta_a_terceros"> $ {{ number_format($venta->cuenta_a_terceros, 2) }}</p>--}}
{{--        @endif--}}
{{--        <p id="total"> <b>$ {{ number_format($venta->total, 2) }}</b></p>--}}
{{--    </div>--}}
</section>

</body>
</html>
