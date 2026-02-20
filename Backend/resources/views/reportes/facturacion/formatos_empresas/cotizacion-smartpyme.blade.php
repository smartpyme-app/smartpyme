<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Cotización SmartPyme #{{ $venta->correlativo }} - {{ $venta->nombre_cliente }}</title>
    <style>

        *{
            margin: 0cm;
            font-family: 'system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue","Noto Sans","Liberation Sans",Arial,sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji"';
        }
        body {
            font-family: serif;
            margin: 50px 50px;
        }
        h1,h2,h3,h4,h5,h6{
            color: #000000 !important;
        }
        p{
            font-size: 14px;
        }
        table{
            width: 100%;
            border-collapse: collapse;
        }
        .table th, .table td{
            border: 0px;
            border-collapse: collapse;
            padding: 10px 5px;
            text-align: left;
        }
        .text-right{
            text-align: right !important;
        }
        .border-bottom{
            border-bottom: 1px solid #000000 !important;
        }


    </style>

</head>
<body>

        <table>
            <tbody>
                <tr>
                    <td width="60%">
                        <h3 style="font-size: 20px;">{{ $venta->empresa()->pluck('nombre')->first() }}</h3>
                        <p>
                            {{ $venta->empresa()->pluck('municipio')->first() }}
                            {{ $venta->empresa()->pluck('departamento')->first() }}
                        </p>
                        <p>{{ $venta->empresa()->pluck('direccion')->first() }}</p>
                        <p>{{ $venta->empresa()->pluck('telefono')->first() }}</p>
                    </td>
                    <td class="text-right">
                        @if ($venta->empresa()->pluck('logo')->first())
                        <figure style="height: 150px; overflow: hidden;">
                            <img style="margin-top: -50px;" width="250" height="250" src="{{ asset('img/'.$venta->empresa()->pluck('logo')->first()) }}" alt="Logo">
                        </figure>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
        <p style="margin-bottom: 15px;">Reciba un cordial saludo de parte del equipo de Smartpyme. Agradecemos su interés por explorar con nosotros la implementación de Inteligencia de Negocios en su empresa a través de nuestro sistema</p>
        <table>
            <tbody>
                <tr>
                    <td><h4>Cliente</h4></td>
                </tr>
                <tr>
                    <td>
                        <p>{{ $venta->nombre_cliente }}</p>
                        <p>
                        @if ($venta->empresa()->pluck('id')->first() != 420)
                            {{ $venta->cliente()->pluck('municipio')->first() }}
                            {{ $venta->cliente()->pluck('departamento')->first() }}
                        @endif
                            {{ $venta->cliente()->pluck('direccion')->first() }} <br>
                        </p>
                    </td>
                    <td>
                        
                        @if ($venta->empresa()->pluck('id')->first() != 420)
                            <p>NCR:{{ $venta->cliente()->pluck('ncr')->first() }}</p>
                            <p>@if($venta->empresa->pais == 'El Salvador')DUI:@else Número de identificación:@endif{{ $venta->cliente()->pluck('dui')->first() }}</p>
                            <p>Teléfono:{{ $venta->cliente()->pluck('telefono')->first() }}</p>
                        @else
                            <p>RTN:{{ $venta->cliente()->pluck('ncr')->first() }}</p>
                            <p>Teléfono:{{ $venta->cliente()->pluck('telefono')->first() }}</p>
                        @endif
                    </td>
                    <td>
                        <p class="text-right">Cotización #{{ $venta->correlativo }}</p>
                        <p class="text-right">Fecha: {{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
                        <p class="text-right">Válido hasta: {{ \Carbon\Carbon::parse($venta->fecha_expiracion)->format('d/m/Y') }}</p>
                    </td>
                </tr>
            </tbody>
        </table>

        <br>

        <table class="table">
            <thead>
                <tr>
                    <th class="border-bottom">Descripción</th>
                    <th class="border-bottom text-right">Cantidad</th>
                    <th class="border-bottom text-right">Precio</th>
                    <th class="border-bottom text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($venta->detalles as $detalle)
                <tr>
                    <td class="border-bottom">   {{ $detalle->nombre_producto  }}</td>
                    <td class="border-bottom text-right">   {{ number_format($detalle->cantidad, 0) }}</td>
                    <td class="border-bottom text-right">   {{ $venta->empresa->currency->currency_symbol }} {{number_format($detalle->precio , 2) }}</td>
                    <td class="border-bottom text-right">   {{ $venta->empresa->currency->currency_symbol }} {{ number_format($detalle->total, 2) }}</th>
                </tr>
                @if ($detalle->descuento > 0)
                    <tr>
                        <td>DESCUENTOS</td>
                        <td></td>
                        <td></td>
                        <td class="text-right">- {{ $venta->empresa->currency->currency_symbol }} {{ number_format($detalle->descuento, 2) }} </th>
                    </tr>
                @endif
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2"></td>
                    <td class="text-right">Subtotal</td>
                    <td class="text-right">{{ $venta->empresa->currency->currency_symbol }} {{ number_format($venta->sub_total, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="2"></td>
                    <td class="text-right">
                        @if ($venta->empresa->pais == 'Honduras')
                            ISV
                        @else
                            IVA
                        @endif
                    </td>
                    <td class="text-right">{{ $venta->empresa->currency->currency_symbol }} {{ number_format($venta->iva, 2) }}</td>
                </tr>¿
                <tr>
                    <td colspan="2"></td>
                    <td class="text-right"><b>Total</b></td>
                    <td class="text-right"><b>{{ $venta->empresa->currency->currency_symbol }} {{ number_format($venta->total, 2) }}</b></td>
                </tr>
            </tfoot>
        </table>

        <br>
        <h4>Términos y condiciones:</h4>
        <p>{!! '• ' . str_replace("\n", "<br>• ", nl2br(e($venta->observaciones))) !!} </p>
        <br>
        @if($venta->empresa->mostrar_sello_firma_cotizacion)
    <table style="width: 100%; margin-top: 30px;">
        <tr>
            <td style="width: 50%; padding: 20px; text-align: center;">
                @if($venta->empresa->firma)
                <img 
                    src="{{ asset('img/'.$venta->empresa->firma) }}" 
                    alt="Firma" 
                    style="max-width: 150px; max-height: 100px;">
                @else
                <div style="height: 100px; line-height: 100px;">(Sin firma)</div>
                @endif
            </td>
            <td style="width: 50%; padding: 20px; text-align: center;">
                @if($venta->empresa->sello)
                <img 
                    src="{{ asset('img/'.$venta->empresa->sello) }}" 
                    alt="Sello" 
                    style="max-width: 150px; max-height: 100px;">
                @else
                <div style="height: 100px; line-height: 100px;">(Sin sello)</div>
                @endif
            </td>
        </tr>
        <tr>
            <td style="width: 50%; padding: 10px; text-align: center;">
                <p>____________________________</p>
                <h4 style="margin: 0; font-size: 16px; color: #333;">Firma</h4>
            </td>
            <td style="width: 50%; padding: 10px; text-align: center;">
                <p>____________________________</p>
                <h4 style="margin: 0; font-size: 16px; color: #333;">Sello</h4>
            </td>
        </tr>
    </table>
    @endif

        <br>
        <br>
        <br>

        <h4>Firma:</h4>
        <p>____________________________</p>



    </section>


</body>
</html>
