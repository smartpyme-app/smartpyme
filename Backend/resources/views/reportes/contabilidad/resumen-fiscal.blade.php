<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Resumen fiscal</title>
    <style>
        /* DejaVu Sans: fuente incluida en DomPDF; símbolos como ₡ € £ no aparecen como "?" */
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #212529; }
        h1, h2 { margin: 4px 0; text-align: center; }
        h3 { margin: 14px 0 6px; font-size: 11px; }
        p { margin: 2px 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { padding: 4px 6px; border: 1px solid #dee2e6; }
        th { background: #f1f3f5; font-weight: bold; }
        .text-right { text-align: right; }
        .cards { width: 100%; margin-bottom: 12px; }
        .cards td { border: 1px solid #dee2e6; padding: 8px; vertical-align: top; width: 33%; }
        .card-label { color: #6c757d; font-size: 9px; margin-bottom: 4px; }
        .card-value { font-size: 13px; font-weight: bold; }
        .muted { color: #6c757d; font-size: 9px; }
    </style>
</head>
<body>
@php
    $empresa = Auth::user()->empresa()->with('currency')->first();
    $simbolo = ($empresa && $empresa->currency) ? $empresa->currency->currency_symbol : '$';
    $periodo = $resumen['periodo'] ?? [];
    $inicio = $periodo['inicio'] ?? null;
    $fin = $periodo['fin'] ?? null;
    $totales = $resumen['totales'] ?? [];
    $iva = $resumen['iva'] ?? [];
    $pago = $resumen['pago_a_cuenta_iva'] ?? [];
    $fmt = fn ($n) => $simbolo.' '.number_format((float) $n, 2);
@endphp

<h1>RESUMEN FISCAL</h1>
<h2>{{ $empresa->nombre ?? '' }}</h2>
<p class="muted" style="text-align: center;">
    @if (!empty($resumen['pais']))
        <strong>País:</strong> {{ $resumen['pais'] }} —
    @endif
    <strong>Período:</strong>
    @if ($inicio && $fin)
        {{ ucfirst(\Carbon\Carbon::parse($inicio)->translatedFormat('F Y')) }}
        ({{ \Carbon\Carbon::parse($inicio)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($fin)->format('d/m/Y') }})
    @endif
</p>

<h3>Resumen del periodo</h3>
<table class="cards">
    <tr>
        <td>
            <div class="card-label">Total ventas</div>
            <div class="card-value">{{ $fmt($totales['ventas'] ?? 0) }}</div>
        </td>
        <td>
            <div class="card-label">Total compras</div>
            <div class="card-value">{{ $fmt($totales['compras'] ?? 0) }}</div>
        </td>
        <td>
            <div class="card-label">Total gastos</div>
            <div class="card-value">{{ $fmt($totales['gastos'] ?? 0) }}</div>
        </td>
    </tr>
</table>

@php
    $renderDesglose = function (string $titulo, array $filas) use ($fmt) {
        if ($filas === []) {
            return;
        }
        $sumaBase = 0;
        $sumaIva = 0;
        echo '<h3>' . e($titulo) . '</h3>';
        echo '<table><thead><tr><th>Concepto</th><th class="text-right">Base</th><th class="text-right">Impuesto</th><th class="text-right">Total</th></tr></thead><tbody>';
        foreach ($filas as $row) {
            $base = (float) ($row['base'] ?? 0);
            $imp = (float) ($row['iva'] ?? 0);
            $sumaBase += $base;
            $sumaIva += $imp;
            echo '<tr><td>' . e($row['etiqueta'] ?? '') . '</td>';
            echo '<td class="text-right">' . $fmt($base) . '</td>';
            echo '<td class="text-right">' . $fmt($imp) . '</td>';
            echo '<td class="text-right">' . $fmt($base + $imp) . '</td></tr>';
        }
        echo '<tr><th>Total</th><th class="text-right">' . $fmt($sumaBase) . '</th><th class="text-right">' . $fmt($sumaIva) . '</th><th class="text-right">' . $fmt($sumaBase + $sumaIva) . '</th></tr>';
        echo '</tbody></table>';
    };
    $renderDesglose('Compras por impuesto', $resumen['compras_por_impuesto'] ?? []);
    $renderDesglose('Ventas por impuesto', $resumen['ventas_por_impuesto'] ?? []);
@endphp

<h3>Resumen de impuestos</h3>
<table class="cards">
    <tr>
        <td>
            <div class="card-label">Crédito</div>
            <div class="card-value">{{ $fmt($iva['iva_a_favor'] ?? 0) }}</div>
        </td>
        <td>
            <div class="card-label">Débito</div>
            <div class="card-value">{{ $fmt($iva['iva_en_contra'] ?? 0) }}</div>
        </td>
        <td>
            <div class="card-label">Diferencia</div>
            <div class="card-value">{{ $fmt($iva['diferencia_estimada_pago_iva'] ?? 0) }}</div>
        </td>
    </tr>
</table>

@if (!empty($pago['aplica']))
    <h3>Pago a cuenta (impuesto)</h3>
    <p><strong>{{ $fmt($pago['monto'] ?? 0) }}</strong></p>
    <p class="muted">{{ $pago['descripcion'] ?? '' }}</p>
@endif

</body>
</html>
