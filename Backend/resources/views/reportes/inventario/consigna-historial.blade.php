<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial consigna</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 16px; text-align: center; margin: 4px 0 12px; }
        h2.titulo-cliente { font-size: 14px; text-align: center; color: #1a4b8c; margin: 0 0 2px; }
        h3.sub-cliente { font-size: 12px; text-align: center; color: #1a4b8c; margin: 0 0 10px; font-weight: normal; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 0.5px solid #666; padding: 3px 5px; vertical-align: top; }
        th { background: #d9e2ef; font-size: 9px; text-align: center; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .section-title { text-align: center; font-weight: bold; margin: 10px 0 6px; font-size: 11px; }
    </style>
</head>
<body>
    <h1>{{ $empresa->nombre ?? 'Empresa' }}</h1>
    <h2 class="titulo-cliente">PRODUCTOS EN CONSIGNA</h2>
  @if (!empty($payload['tercero']['nombre']))
        <h3 class="sub-cliente">{{ $payload['tercero']['nombre'] }}</h3>
    @endif

    <table>
        <thead>
            <tr>
                <th>CÓDIGO</th>
                <th>PRODUCTO</th>
                @if (!empty($payload['incluir_lote']))
                    <th>LOTE</th>
                    <th>VENCE</th>
                @endif
                <th>Bodega</th>
                <th>{{ $alcance === 'compras' ? 'RECIBIDAS' : 'ENVIADAS' }}</th>
                <th>{{ $alcance === 'compras' ? 'VENDIDAS' : 'FACTURADAS' }}</th>
                <th>DEVUELTAS</th>
                <th>DISP</th>
                <th>PR/UN</th>
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
                    <td class="text-end">{{ number_format((float) ($fila['precio_unitario'] ?? 0), 6, '.', '') }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ !empty($payload['incluir_lote']) ? 10 : 8 }}" class="text-center">Sin productos en consigna</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="section-title">. : HISTORIAL DE MOVIMIENTOS : .</p>
    <table>
        <thead>
            <tr>
                <th>FECHA</th>
                <th>TIPO</th>
                <th>CÓDIGO</th>
                <th>PRODUCTO</th>
                <th>CANTIDAD</th>
                @if (!empty($payload['incluir_lote']))
                    <th>LOTE</th>
                @endif
                <th>NOTAS</th>
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