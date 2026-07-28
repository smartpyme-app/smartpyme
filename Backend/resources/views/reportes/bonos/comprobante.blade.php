<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Comprobante de bono - {{ $bono->vendedor?->name }}</title>
    <style>
        * { margin: 0; font-family: DejaVu Sans, sans-serif; }
        body { margin: 40px; color: #333; font-size: 12px; }
        h1 { color: #005CBB; font-size: 20px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
        .text-right { text-align: right; }
        .meta { margin-top: 16px; line-height: 1.6; }
        .firma { margin-top: 60px; }
        .firma-linea { border-top: 1px solid #333; width: 260px; margin: 48px auto 8px; }
        .firma-texto { text-align: center; color: #666; }
        .total-row td { font-weight: bold; background: #f0f8ff; }
    </style>
</head>
<body>
    <h1>Comprobante de bono</h1>
    <p>Generado el {{ now()->format('d/m/Y H:i') }}</p>

    <div class="meta">
        @if($empresa)
            <p><strong>Empresa:</strong> {{ $empresa->nombre }}</p>
        @endif
        <p><strong>Vendedor:</strong> {{ $bono->vendedor?->name ?? ('#' . $bono->id_vendedor) }}</p>
        <p><strong>Regla:</strong> {{ $bono->regla?->nombre ?? ('#' . $bono->id_regla) }}</p>
        <p><strong>Período:</strong> {{ $bono->periodo_inicio?->format('d/m/Y') }} al {{ $bono->periodo_fin?->format('d/m/Y') }}</p>
        <p><strong>Estado:</strong> {{ ucfirst($bono->estado) }}</p>
        @if($bono->aprobado_at)
            <p><strong>Aprobado:</strong> {{ $bono->aprobado_at->format('d/m/Y H:i') }}
                @if($bono->aprobadoPor)
                    por {{ $bono->aprobadoPor->name }}
                @endif
            </p>
        @endif
        @if($bono->pagado_at)
            <p><strong>Pagado:</strong> {{ $bono->pagado_at->format('d/m/Y H:i') }}</p>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Concepto</th>
                <th class="text-right">Monto</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Ventas base del período</td>
                <td class="text-right">{{ number_format((float) $bono->monto_ventas_base, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td>Bono</td>
                <td class="text-right">{{ number_format((float) $bono->monto, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="firma">
        <div class="firma-linea"></div>
        <p class="firma-texto">Firma del vendedor</p>
    </div>
</body>
</html>
