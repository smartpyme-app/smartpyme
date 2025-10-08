<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Cotización Grupo Split #{{ $venta->correlativo }} - {{ $venta->nombre_cliente }}</title>
    <style>

        *{
            margin: 0cm;
            font-family: 'system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue","Noto Sans","Liberation Sans",Arial,sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji"';
        }
        body {
            font-family: serif;
            margin: 50px;
        }
        h1,h2,h3,h4,h5,h6{
            color: #000000 !important;
        }

        table{
            width: 100%;
            border-collapse: collapse;
        }
        .table th, .table td{
            border: 0px;
            border-collapse: collapse;
            padding: 5px 5px;
            text-align: left;
            font-size: 12px;
        }
        p{
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


    </style>

</head>
<body>
{{-- <body onload="javascript:print();"> --}}

        <table>
            <tbody>
                <tr>
                    <td width="40%" class="text-left" style="vertical-align: top;">
                        <img height="100" src="{{ asset('img/logo-grupo-split.jpg') }}" alt="Logo">
                        <br>
                        <p>
                        @php
                            setlocale(LC_TIME, 'es_ES.UTF-8');
                            $fecha = \Carbon\Carbon::parse($venta->fecha);
                            $dia = $fecha->format('d');
                            $mes = ucfirst(strftime('%B', $fecha->timestamp));
                            $anio = $fecha->format('Y');
                        @endphp
                        San Salvador, {{ $dia }} de {{ $mes }} {{ $anio }}
                        <br>
                        Señores, <br> <br>
                        {{ $venta->empresa()->pluck('nombre')->first() }} <br>
                        Presente
                        <br> <br>
                        </p>
                    </td>
                    <td class="text-right" style="vertical-align: top;">
                        <h2>{{ $venta->empresa()->pluck('nombre')->first() }}</h2>
                        Cotización N°: {{ $venta->correlativo }} <br> 

                        <br>
                        <br>
                        <br>
                        <p>
                        Atención a: <br>
                        {{ $venta->nombre_cliente }}
                        </p>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <p>
                        Por medio de la presente, nuestra empresa agradece su preferencia y pone a su disposición la siguiente propuesta de precios, esperando que pueda   
                        adaptarse a su necesidad. De antemano, gracias por permitirnos ofrecerle nuestro servicio.  
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <br>

        <table class="table">
            <thead>
                <tr>
                    <th style="width: 10%;" class="border-bottom">CÓDIGO</th>
                    <th style="width: 40%;" class="border-bottom">NOMBRE DEL ARTÍCULO</th>
                    <th style="width: 10%;" class="border-bottom text-right">CANTIDAD</th>
                    <th style="width: 10%;" class="border-bottom text-right">UNITARIO</th>
                    <th style="width: 10%;" class="border-bottom text-right">IMPORTE</th>
                </tr>
            </thead>
            <tbody>
                @foreach($venta->detalles as $detalle)
                <tr>
                    <td class="border-bottom">   {{ $detalle->producto->codigo ?? $detalle->producto->barcode }}</td>
                    <td class="border-bottom">   {{ $detalle->nombre_producto  }}</td>
                    <td class="border-bottom text-right">   {{ number_format($detalle->cantidad, 0) }}</td>
                    <td class="border-bottom text-right">   {{ $venta->empresa->currency->currency_symbol }} {{number_format($detalle->precio , 2) }}</td>
                    <td class="border-bottom text-right">   {{ $venta->empresa->currency->currency_symbol }} {{ number_format($detalle->total, 2) }}</th>
                </tr>
                @if ($detalle->descuento > 0)
                    <tr>
                        <td></td>
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
                    <td colspan="3" rowspan="5" style="vertical-align: top;">
                        <h6>CONDICIONES GENERALES DE SUMINISTRO:</h6>
                        <p>{!! nl2br(e($venta->observaciones))  !!} </p>

                    </td>
                    <td class="text-right">SUBTOTAL</td>
                    <td class="text-right">{{ $venta->empresa->currency->currency_symbol }} {{ number_format($venta->sub_total, 2) }}</td>
                </tr>
                <tr>
                    <td class="text-right">
                        @if ($venta->empresa->pais == 'Honduras')
                            ISV
                        @else
                            IVA
                        @endif
                    </td>
                    <td class="text-right">{{ $venta->empresa->currency->currency_symbol }} {{ number_format($venta->iva, 2) }}</td>
                </tr>
                <tr>
                    <td class="text-right">RETENCIÓN</td>
                    <td class="text-right">{{ $venta->empresa->currency->currency_symbol }} {{ number_format($venta->iva_retenido, 2) }}</td>
                </tr>
                <tr>
                    <td class="text-right">PERCEPCIÓN</td>
                    <td class="text-right">{{ $venta->empresa->currency->currency_symbol }} {{ number_format($venta->iva_percibido, 2) }}</td>
                </tr>
                <tr>
                    <td class="text-right"><b>TOTAL</b></td>
                    <td class="text-right"><b>{{ $venta->empresa->currency->currency_symbol }} {{ number_format($venta->total, 2) }}</b></td>
                </tr>
            </tfoot>
        </table>

        <br>

        <p class="text-center">Agradeciendo su atención y esperando nuestra cotización sea conveniente a los intereses de su vivienda o empresa, aprovechamos la   
        ocasión, para saludarles.  </p>
        <br>

        <table style="width: 100%; margin-top: 30px;">
            <tr>
                <td style="width: 20%; padding: 20px; text-align: center;">
                    Atentamente,
                </td>
                <td style="width: 40%; padding: 20px; text-align: center;">
                    ____________________________ <br>
                    GRUPO SPLIT SA DE CV
                </td>
                <td style="width: 40%; padding: 20px; text-align: center;">
                    ____________________________ <br>
                    Firma y sello de aceptado
                </td>
            </tr>
        </table>

        <p class="text-center" style="font-size: 10px;">
            GRUPOSPLIT SA DE CV  <br>
            Ciudad Merliot, Pol J #25 Antiguo Cuscatlan, La Libertad  <br>
            PBX: (503) 2524-4230 | CEL: (503) 7656 2576 | www.gruposplit.com.sv  <br>
            <span style="color: darkblue;">GRUPO SPLIT | LIDER EN AIRES ACONDICIONADOS | EL SALVADOR</span>  <br>
        </p>
        <br>
        <p style="margin-left: 300px;">
            <span>Distribuidor Autorizado de:</span>
            <img style=" height: 70px; margin-left: 50px; margin-top: 20px;" src="{{ asset('img/logo-grupo-split-2.jpg') }}" alt="Logo">
        </p>


    </section>


</body>
</html>
