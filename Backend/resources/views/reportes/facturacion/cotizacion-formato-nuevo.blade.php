<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Cotización #{{ $venta->correlativo }} - {{ $venta->nombre_cliente }}</title>
    @php
        $brand = $cotizacion_color_marca ?? '#0a2f7a';
    @endphp
    <style>
        * { margin: 0; font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; line-height: 1.45; color: #111; margin: 40px 45px; }
        h1, h2, h3, h4 { color: #111 !important; }
        .brand { color: {{ $brand }}; }
        .rule { border: none; border-top: 1px solid #bdbdbd; margin: 12px 0; }
        .meta { text-align: right; font-size: 10px; font-weight: bold; }
        .section-title { font-size: 12px; font-weight: bold; margin: 14px 0 8px; color: {{ $brand }}; }
        .text-right { text-align: right !important; }
        table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 8px 5px; text-align: left; vertical-align: top; }
        .table td { border-bottom: 1px solid #e0e0e0; }
        .carta p { margin: 0 0 8px; text-align: justify; }
        .legal p { margin: 0 0 8px; text-align: justify; }
        thead { display: table-header-group; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="width: 65%; vertical-align: top;">
                <h2 style="font-size: 16px; margin-bottom: 4px;">{{ $venta->empresa->nombre }}</h2>
                <p>
                    {{ $venta->empresa->municipio }} {{ $venta->empresa->departamento }}
                </p>
                @if ($venta->empresa->direccion)
                    <p>{{ $venta->empresa->direccion }}</p>
                @endif
                @if ($venta->empresa->telefono)
                    <p><strong>Contacto:</strong> {{ $venta->empresa->telefono }}</p>
                @endif
            </td>
            <td class="text-right" style="vertical-align: top;">
                @if ($venta->empresa->logo)
                    <img src="{{ asset('img/' . $venta->empresa->logo) }}" alt="Logo" width="130" height="130" style="max-width:130px;max-height:130px;">
                @endif
            </td>
        </tr>
    </table>

    <div class="carta">
        @foreach (preg_split('/\r\n|\r|\n/', (string) ($cotizacion_carta ?? '')) as $parrafo)
            @if (trim($parrafo) !== '')
                <p>{!! nl2br(e($parrafo)) !!}</p>
            @endif
        @endforeach
    </div>

    <hr class="rule">

    <table>
        <tr>
            <td><h4>Cliente</h4></td>
        </tr>
        <tr>
            <td>
                <p><strong>{{ $venta->nombre_cliente }}</strong></p>
                <p>
                    @if ($venta->empresa->id != 420)
                        {{ $venta->cliente->municipio ?? '' }}
                        {{ $venta->cliente->departamento ?? '' }}
                    @endif
                    {{ $venta->cliente->direccion ?? '' }}
                </p>
            </td>
            <td>
                @if ($venta->empresa->id != 420)
                    @if ($venta->empresa->pais == 'El Salvador')
                        <p>NCR: {{ $venta->cliente->ncr ?? '' }}</p>
                        <p>DUI: {{ $venta->cliente->dui ?? '' }}</p>
                    @else
                        <p>Registro tributario: {{ $venta->cliente->ncr ?? '' }}</p>
                        <p>Número de identificación: {{ $venta->cliente->dui ?? '' }}</p>
                    @endif
                    <p>Teléfono: {{ $venta->cliente->telefono ?? '' }}</p>
                @else
                    <p>RTN: {{ $venta->cliente->ncr ?? '' }}</p>
                    <p>Teléfono: {{ $venta->cliente->telefono ?? '' }}</p>
                @endif
            </td>
            <td class="meta">
                <p>Cotización #{{ $venta->correlativo }}</p>
                <p>Fecha {{ \Carbon\Carbon::parse($venta->fecha)->format('d/m/Y') }}</p>
                @if (! empty($venta->fecha_expiracion))
                    <p>Válido hasta {{ \Carbon\Carbon::parse($venta->fecha_expiracion)->format('d/m/Y') }}</p>
                @endif
            </td>
        </tr>
    </table>

    <br>

    @include('reportes.facturacion.partials.cotizacion-tabla-lineas', ['cotizacion_color_marca' => $brand])

    @php
        $obs = trim((string) $venta->observaciones);
    @endphp
    @if ($obs !== '')
        <p class="section-title">Observaciones</p>
        <p class="legal">{!! nl2br(e($venta->observaciones)) !!}</p>
    @endif

    <p class="section-title">Términos y condiciones</p>
    <div class="legal">
        @foreach (preg_split('/\r\n|\r|\n/', (string) ($cotizacion_terminos ?? '')) as $linea)
            @if (trim($linea) !== '')
                <p>{!! nl2br(e($linea)) !!}</p>
            @endif
        @endforeach
    </div>

    <p class="section-title">Compromisos</p>
    <div class="legal">
        <ul style="margin: 0 0 10px 18px; padding: 0;">
            @foreach (preg_split('/\r\n|\r|\n/', (string) ($cotizacion_compromisos ?? '')) as $linea)
                @if (trim($linea) !== '')
                    <li style="margin-bottom: 6px;">{!! nl2br(e($linea)) !!}</li>
                @endif
            @endforeach
        </ul>
    </div>

    <p>Quedamos atentos a cualquier consulta o información adicional que necesite.</p>

    @if ($venta->empresa->mostrar_sello_firma_cotizacion)
        <table style="width: 100%; margin-top: 24px;">
            <tr>
                <td style="width: 50%; text-align: center;">
                    @if ($venta->empresa->firma)
                        <img src="{{ asset('img/' . $venta->empresa->firma) }}" alt="Firma" style="max-width: 150px; max-height: 100px;">
                    @endif
                    <p>____________________________</p>
                    <strong>Firma</strong>
                </td>
                <td style="width: 50%; text-align: center;">
                    @if ($venta->empresa->sello)
                        <img src="{{ asset('img/' . $venta->empresa->sello) }}" alt="Sello" style="max-width: 150px; max-height: 100px;">
                    @endif
                    <p>____________________________</p>
                    <strong>Sello</strong>
                </td>
            </tr>
        </table>
    @else
        <p style="margin-top: 20px;"><strong>Firma.</strong> <strong>Sello.</strong></p>
    @endif
</body>
</html>