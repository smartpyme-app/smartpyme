<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Comprobante de incentivos - {{ $vendedor->name }}</title>
    <style>
        * { margin: 0; font-family: DejaVu Sans, sans-serif; }
        body { margin: 40px; color: #333; font-size: 12px; }
        h1, h2 { color: #005CBB; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        h2 { font-size: 16px; margin: 24px 0 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
        .text-right { text-align: right; }
        .meta { margin-top: 16px; line-height: 1.6; }
        .firma { margin-top: 60px; }
        .firma-linea { border-top: 1px solid #333; width: 260px; margin: 48px auto 8px; }
        .firma-texto { text-align: center; color: #666; }
        .total-row td { font-weight: bold; background: #f0f8ff; }
        .resumen-total { margin-top: 24px; padding: 12px; background: #f0f8ff; border: 1px solid #ccc; }
        .resumen-total p { margin: 4px 0; }
    </style>
</head>
<body>
    <h1>Comprobante de comisión e incentivos</h1>
    <p>Generado el {{ now()->format('d/m/Y H:i') }}</p>

    <div class="meta">
        <p><strong>Vendedor:</strong> {{ $vendedor->name }}</p>
        @if($vendedor->email)
            <p><strong>Correo:</strong> {{ $vendedor->email }}</p>
        @endif
        <p><strong>Período:</strong> {{ $periodo->fecha_inicio->format('d/m/Y') }} al {{ $periodo->fecha_fin->format('d/m/Y') }}</p>
        <p><strong>Estado del período:</strong> {{ ucfirst($periodo->estado) }}</p>
    </div>

    <h2>Detalle de comisiones</h2>
    <table>
        <thead>
            <tr>
                <th>Correlativo</th>
                <th>Fecha</th>
                <th>Categoría</th>
                <th>Origen</th>
                <th class="text-right">Monto base</th>
                <th class="text-right">%</th>
                <th class="text-right">Comisión</th>
            </tr>
        </thead>
        <tbody>
            @forelse($movimientos as $movimiento)
                <tr>
                    <td>{{ $movimiento->venta?->correlativo ?? '—' }}</td>
                    <td>{{ $movimiento->fecha_evento?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $movimiento->categoria?->nombre ?? '—' }}</td>
                    <td>{{ \App\Services\Comisiones\ComisionReporteService::etiquetaOrigen($movimiento->origen) }}</td>
                    <td class="text-right">{{ number_format((float) $movimiento->monto_base, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $movimiento->porcentaje_aplicado, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $movimiento->monto_comision, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Sin movimientos en este período.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="6" class="text-right">Total comisión</td>
                <td class="text-right">{{ number_format($total, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    @if(!empty($bonos))
        <h2>Detalle de bonos</h2>
        <table>
            <thead>
                <tr>
                    <th>Regla</th>
                    <th>Estado</th>
                    <th class="text-right">Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bonos as $bono)
                    <tr>
                        <td>{{ $bono['nombre'] }}</td>
                        <td>{{ ucfirst($bono['estado']) }}</td>
                        <td class="text-right">{{ number_format((float) $bono['monto'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="2" class="text-right">Bonos aprobados o pagados</td>
                    <td class="text-right">{{ number_format((float) $totalAPagar['bonos_aprobados_o_pagados'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <div class="resumen-total">
        <p><strong>Total a pagar (desglosado)</strong></p>
        <p>Comisiones: {{ number_format((float) $totalAPagar['comisiones'], 2) }}</p>
        <p>Bonos (aprobados/pagados): {{ number_format((float) $totalAPagar['bonos_aprobados_o_pagados'], 2) }}</p>
        <p><strong>Total general: {{ number_format((float) $totalAPagar['comisiones'] + (float) $totalAPagar['bonos_aprobados_o_pagados'], 2) }}</strong></p>
    </div>

    <div class="firma">
        <div class="firma-linea"></div>
        <p class="firma-texto">Firma del vendedor</p>
    </div>
</body>
</html>
