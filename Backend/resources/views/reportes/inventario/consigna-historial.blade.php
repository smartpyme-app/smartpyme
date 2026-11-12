<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial consigna</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; }
        h1, h2, h3 { text-align: center; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 0.5px solid #999; padding: 4px 6px; }
        th { background: #f0f0f0; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .section-title { font-weight: bold; margin: 12px 0 6px; text-transform: uppercase; }
    </style>
</head>
<body>
    <h1>{{ $empresa->nombre ?? 'Empresa' }}</h1>
    <h2>Historial de consigna — {{ $alcance === 'compras' ? 'Compras (proveedor)' : 'Ventas (cliente)' }}</h2>
    @if (!empty($payload['tercero']['nombre']))
        <h3>{{ $payload['tercero']['nombre'] }}</h3>
    @endif

    <p class="section-title">Productos en consigna</p>
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Producto</th>
                @if (!empty($payload['incluir_lote']))
                    <th>Lote</th>
                    <th>Vence</th>
                @endif
                <th>Bodega</th>
                <th class="text-end">{{ $alcance === 'compras' ? 'Recibidas' : 'Enviadas' }}</th>
                <th class="text-end">{{ $alcance === 'compras' ? 'Vendidas' : 'Facturadas' }}</th>
                <th class="text-end">Devueltas</th>
                <th class="text-end">Disponible</th>
                <th class="text-end">Pr/un</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payload['resumen'] ?? [] as $fila)
                <tr>
                    <td>{{ $fila['codigo'] ?? '' }}</td>
                    <td>{{ $fila['producto'] ?? '' }}</td>
                    @if (!empty($payload['incluir_lote']))
                        <td>{{ $fila['lote'] ?? '' }}</td>
                        <td>{{ $fila['vence'] ?? '' }}</td>
                    @endif
                    <td>{{ $fila['bodega'] ?? '' }}</td>
                    <td class="text-end">{{ $fila['enviadas'] ?? 0 }}</td>
                    <td class="text-end">{{ $fila['facturadas'] ?? 0 }}</td>
                    <td class="text-end">{{ $fila['devueltas'] ?? 0 }}</td>
                    <td class="text-end">{{ $fila['disponible'] ?? 0 }}</td>
                    <td class="text-end">{{ number_format((float) ($fila['precio_unitario'] ?? 0), 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ !empty($payload['incluir_lote']) ? 10 : 8 }}" class="text-center">Sin productos en consigna</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="section-title">Historial de movimientos</p>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Código</th>
                <th>Producto</th>
                <th class="text-end">Cant.</th>
                @if (!empty($payload['incluir_lote']))
                    <th>Lote</th>
                @endif
                <th>Notas</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payload['movimientos'] ?? [] as $m)
                <tr>
                    <td>{{ $m['fecha'] ?? '' }}</td>
                    <td>{{ $m['tipo'] ?? '' }}</td>
                    <td>{{ $m['codigo'] ?? '' }}</td>
                    <td>{{ $m['producto'] ?? '' }}</td>
                    <td class="text-end">{{ $m['cantidad'] ?? 0 }}</td>
                    @if (!empty($payload['incluir_lote']))
                        <td>{{ $m['lote'] ?? '' }}</td>
                    @endif
                    <td>{{ $m['notas'] ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ !empty($payload['incluir_lote']) ? 7 : 6 }}" class="text-center">Sin movimientos</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
