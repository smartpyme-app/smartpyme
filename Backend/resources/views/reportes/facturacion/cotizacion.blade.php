<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Cotización #{{ $venta->correlativo }} - {{ $venta->nombre_cliente }}</title>
    <style>
        * {
            margin: 0cm;
            font-family: DejaVu Sans, sans-serif;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            margin: 50px;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            color: #000000 !important;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            border: 0px;
            border-collapse: collapse;
            padding: 10px 5px;
            text-align: left;
        }

        .text-right {
            text-align: right !important;
        }

        .border-bottom {
            border-bottom: 1px solid #000000 !important;
        }
    </style>

</head>

<body>
    {{-- <body onload="javascript:print();"> --}}

    <table>
        <tbody>
            <tr>
                <td>
                    <h1>{{ $venta->empresa()->pluck('nombre')->first() }}</h1>
                    <p>
                        {{ $venta->empresa()->pluck('municipio')->first() }}
                        {{ $venta->empresa()->pluck('departamento')->first() }}
                    </p>
                    <p>{{ $venta->empresa()->pluck('direccion')->first() }}</p>
                    <p>{{ $venta->empresa()->pluck('telefono')->first() }}</p>
                </td>
                <td class="text-right">
                        @if ($venta->empresa()->pluck('logo')->first())
                            <img width="150" height="150" src="{{ asset('img/'.$venta->empresa()->pluck('logo')->first()) }}" alt="Logo">
                        @endif
                    </td>
            </tr>
        </tbody>
    </table>

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
                            @if($venta->empresa->pais == 'El Salvador')
                                <p>NCR:{{ $venta->cliente()->pluck('ncr')->first() }}</p>
                                <p>DUI:{{ $venta->cliente()->pluck('dui')->first() }}</p>
                            @else
                                <p>Registro tributario:{{ $venta->cliente()->pluck('ncr')->first() }}</p>
                                <p>Número de identificación:{{ $venta->cliente()->pluck('dui')->first() }}</p>
                            @endif
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

        @include('reportes.facturacion.partials.cotizacion-tabla-lineas')

        <br>
        <h4>Términos y condiciones:</h4>
{{--        <p>{{ $venta->observaciones }}</p>--}}
        <p>{!! nl2br(e($venta->observaciones))  !!} </p>
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
