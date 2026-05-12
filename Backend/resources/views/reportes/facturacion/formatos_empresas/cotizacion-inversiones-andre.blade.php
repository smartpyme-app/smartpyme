<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Cotización Andre #{{ $venta->correlativo }} - {{ $venta->nombre_cliente }}</title>
    <style>

        *{
            margin: 0cm;
            font-family: 'system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue","Noto Sans","Liberation Sans",Arial,sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji"';
        }
        body {
            font-family: serif;
            margin: 50px;
        }
        h2,h3,h4,h5,h6{
            color: #000000 !important;
        }
        p{
            font-size: 13px;
        }

        table{
            width: 100%;
            border-collapse: collapse;
        }
        .table th, .table td{
            border: 1px solid #111;
            border-collapse: collapse;
            padding: 5px;
            text-align: left;
            font-size: 13px;
        }
        .text-right{
            text-align: right !important;
        }
        .text-center{
            text-align: center !important;
        }
        .border-bottom{
            border-bottom: 1px solid #000000 !important;
        }
        .bg-primary{
            color: #fff !important;
            background: #005 !important;
        }


    </style>

</head>
<body>
        <table>
            <tbody>
                <tr>
                    <td class="text-left">
                        @if ($venta->empresa()->pluck('logo')->first())
                            <img height="100" src="{{ asset('img/'.$venta->empresa()->pluck('logo')->first()) }}" alt="Logo">
                        @endif
                    </td>
                    <td>
                        <h1 style="color: red; margin: 0px;">COTIZACIÓN</h1>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" class="text-center">
                        <h1 style="font-size: 24px;">{{ $venta->empresa()->pluck('nombre')->first() }}</h1>
                        <p style="color: blue;">Soluciones para sus necesidades de sellado de fluidos</p>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <p><b>RTN.05019013561871 </b></p>
                        <p>
                            Real del Puente Casa K#9 <br>
                            Villanueva. Cortes <br>
                            Teléfono: 2670-1407 Servicio al cliente: 3324-9180
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        <table>
            <tbody>
                <tr>
                    <td>
                        <p>{{ $venta->nombre_cliente }}</p>
                        <p>
                            {{ $venta->cliente->direccion ?? $venta->cliente->empresa_direccion  }} <br>
                            ATENCION: {{ $venta->cliente->nombre  }} <br>
                            TEL: {{ $venta->cliente->telefono  }}
                        </p>
                        {{-- <p>Comentarios o instrucciones especiales: {{ $venta->observaciones }}</p> --}}
                    </td>
                    <td>
                        <p class="text-right">Fecha: {{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
                        <p class="text-right">Cotización: {{ $venta->correlativo }}</p>
                        <p class="text-right">ID Cliente: {{ $venta->cliente->codigo_cliente }}</p>
                        <p class="text-right">Válido hasta: {{ \Carbon\Carbon::parse($venta->fecha_expiracion)->format('d/m/Y') }}</p>
                    </td>
                </tr>
            </tbody>
        </table>
        <br>
        <table class="table">
            <thead>
                <tr>
                    <th class="bg-primary">VENDEDOR</th>
                    <th class="bg-primary">NUMERO DE SOLICITUD</th>
                    <th class="bg-primary">FECHA DE ENTREGA</th>
                    <th class="bg-primary">ENTREGADO EN</th>
                    <th class="bg-primary">PUNTO F.O.B</th>
                    <th class="bg-primary">TERMINOS</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $venta->vendedor->name }}</td>
                    <td>{{ $venta->correlativo }}</td>
                    <td>INMEDIATA</td>
                    <td>BODEGA</td>
                    <td></td>
                    <td>
                        {{-- {{ \Carbon\Carbon::parse($venta->fecha)->diffInDays(\Carbon\Carbon::parse($venta->fecha_expiracion), false) }} --}}
                        {{ $venta->observaciones }}
                    </td>
                </tr>
            </tbody>
        </table>

        <br>

        <table class="table">
            <thead>
                <tr>
                    <th class="border-bottom">CANT</th>
                    <th class="border-bottom">ITEM #</th>
                    <th class="border-bottom">DESCRIPCIÓN</th>
                    <th class="border-bottom text-right">PRECIO UNI</th>
                    <th class="border-bottom text-right">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach($venta->detalles as $detalle)
                <tr>
                    <td class="border-bottom"> {{ number_format($detalle->cantidad, 0) }}</td>
                    <td class="border-bottom">{{ optional($detalle->producto)->codigo }}</td>
                    <td class="border-bottom">@include('reportes.facturacion.partials.cotizacion-detalle-descripcion')</td>
                    <td class="border-bottom text-right">   {{ $venta->empresa->currency->currency_symbol }} {{number_format($detalle->precio , 2) }}</td>
                    <td class="border-bottom text-right">   {{ $venta->empresa->currency->currency_symbol }} {{ number_format($detalle->total, 2) }}</th>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-right">SUB TOTAL EN LEMPIRAS</td>
                    <td class="text-right">{{ $venta->empresa->currency->currency_symbol }} {{ number_format($venta->sub_total, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="4" class="text-right">TASA DE IMPUESTO</td>
                    <td class="text-right">15%</td>
                </tr>
                <tr>
                    <td colspan="4" class="text-right">IMPUESTO A LAS VENTAS</td>
                    <td class="text-right">{{ $venta->empresa->currency->currency_symbol }} {{ number_format($venta->iva, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="4" class="text-right"><b>TOTAL EN LEMPIRAS</b></td>
                    <td class="text-right"><b>{{ $venta->empresa->currency->currency_symbol }} {{ number_format($venta->total, 2) }}</b></td>
                </tr>
            </tfoot>
        </table>
        <br><br>
        <p class="text-center">
            Si desea realizar alguna consulta con respecto a esta cotización, póngase en contacto con: <br>
            Margarita Castañeda al # 3324-9180 o al Correo mcastaneda@inversionesandre.com
        </p>
        <br>
        <h3 class="text-center">!GRACIAS POR SU COMPRA!</h3>



    </section>


</body>
</html>
