<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Documento de entrega - Traslado</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #222;
        }
        body {
            font-size: 11px;
            margin: 28px 36px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        .muted { color: #555; font-size: 9px; line-height: 1.35; }
        .empresa-nombre {
            font-size: 13px;
            font-weight: bold;
            line-height: 1.25;
            margin-bottom: 3px;
        }
        .doc-title {
            font-size: 14px;
            font-weight: bold;
            margin: 14px 0 10px 0;
            padding-bottom: 6px;
            border-bottom: 1.5px solid #222;
        }
        .meta td {
            vertical-align: top;
            padding: 2px 8px 2px 0;
            font-size: 11px;
            line-height: 1.45;
        }
        .label { color: #555; }
        .items th {
            border-bottom: 1px solid #222;
            padding: 6px 4px;
            font-size: 10px;
            text-align: left;
            background: #f4f4f4;
        }
        .items td {
            border-bottom: 0.5px solid #ccc;
            padding: 6px 4px;
            font-size: 11px;
        }
        .items tfoot td {
            border-bottom: none;
            padding-top: 8px;
            font-weight: bold;
            background: #f8f8f8;
        }
        .sku { font-size: 9px; color: #666; }
        .firmas {
            margin-top: 42px;
        }
        .firmas td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding: 0 18px;
        }
        .firma-linea {
            border-top: 1px solid #333;
            margin-top: 48px;
            padding-top: 6px;
            font-size: 10px;
        }
    </style>
</head>
<body>
    @php
        $primer = $traslados->first();
        $simbolo = optional($empresa->currency)->currency_symbol ?? '$';
        $totalCosto = $traslados->sum(function ($t) {
            return ($t->costo ?? 0) * $t->cantidad;
        });
        $ubicacion = trim(implode(' / ', array_filter([
            trim(($empresa->municipio ?? '') . ' ' . ($empresa->departamento ?? '')),
            $empresa->direccion ?? null,
            $empresa->telefono ?? null,
        ])));
        $origen = $primer->origen->nombre ?? $primer->nombre_origen ?? 'N/A';
        $destino = $primer->destino->nombre ?? $primer->nombre_destino ?? 'N/A';
    @endphp

    <table>
        <tr>
            <td style="width: 72%; vertical-align: top; padding-right: 12px;">
                <div class="empresa-nombre">{{ $empresa->nombre }}</div>
                @if($ubicacion)
                    <p class="muted">{{ $ubicacion }}</p>
                @endif
            </td>
            <td class="text-right" style="width: 28%; vertical-align: top;">
                @if ($empresa->logo)
                    <img height="64" src="{{ asset('img/'.$empresa->logo) }}" alt="Logo">
                @endif
            </td>
        </tr>
    </table>

    <div class="doc-title">Documento de entrega — Traslado de inventario</div>

    <table class="meta">
        <tr>
            <td style="width: 50%;">
                <p><span class="label">De:</span> <b>{{ $origen }}</b></p>
                <p><span class="label">Para:</span> <b>{{ $destino }}</b></p>
            </td>
            <td style="width: 50%;">
                <p><span class="label">Fecha:</span> {{ \Carbon\Carbon::parse($primer->created_at)->format('d/m/Y') }}</p>
                <p><span class="label">Estado:</span> {{ $primer->estado }}</p>
                <p><span class="label">Realizado por:</span> {{ $primer->usuario->name ?? 'N/A' }}</p>
                <p><span class="label">Productos:</span> {{ $traslados->count() }}</p>
            </td>
        </tr>
        @if($primer->concepto)
        <tr>
            <td colspan="2">
                <p><span class="label">Concepto:</span> {{ $primer->concepto }}</p>
            </td>
        </tr>
        @endif
    </table>

    <br>

    <table class="items">
        <thead>
            <tr>
                <th>Producto</th>
                <th class="text-center" style="width: 80px;">Cantidad</th>
                <th class="text-right" style="width: 110px;">Costo unitario</th>
                <th class="text-right" style="width: 90px;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($traslados as $traslado)
            <tr>
                <td>
                    {{ $traslado->producto->nombre ?? $traslado->nombre_producto ?? 'N/A' }}
                    @if($traslado->producto && !empty($traslado->producto->nombre_variante))
                        - {{ $traslado->producto->nombre_variante }}
                    @endif
                    @if($traslado->producto && $traslado->producto->codigo)
                        <br><span class="sku">SKU: {{ $traslado->producto->codigo }}</span>
                    @endif
                </td>
                <td class="text-center">{{ number_format($traslado->cantidad, 0) }}</td>
                <td class="text-right">{{ $simbolo }}{{ number_format($traslado->costo ?? 0, 2) }}</td>
                <td class="text-right">{{ $simbolo }}{{ number_format(($traslado->costo ?? 0) * $traslado->cantidad, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="text-right">Total</td>
                <td class="text-right">{{ $simbolo }}{{ number_format($totalCosto, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <table class="firmas">
        <tr>
            <td>
                <div class="firma-linea">
                    <b>Entregado por</b><br>
                    {{ $origen }}<br>
                    Firma y sello
                </div>
            </td>
            <td>
                <div class="firma-linea">
                    <b>Recibido por</b><br>
                    {{ $destino }}<br>
                    Firma y sello
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
