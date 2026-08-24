<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Antigüedad de saldos — {{ strtoupper($reporte['tipo']) }}</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; margin: 40px; color: #000; }
        .title { font-size: 16px; font-weight: bold; text-align: center; text-transform: uppercase; margin-bottom: 6px; }
        .subtitle { text-align: center; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #606060; padding: 4px 5px; }
        th { font-weight: bold; text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .totales { font-weight: bold; }
    </style>
</head>
<body>
    @php
        $activos = $reporte['buckets_activos'] ?? array_keys($labels);
        $esIndividual = ($reporte['modo'] ?? '') === 'individual';
        $tipoLabel = ($reporte['tipo'] ?? 'cxc') === 'cxp' ? 'Cuentas por pagar' : 'Cuentas por cobrar';
    @endphp

    <div class="title">Antigüedad de saldos</div>
    <div class="subtitle">
        {{ $tipoLabel }}
        @if($esIndividual && !empty($reporte['entidad']['nombre']))
            — {{ $reporte['entidad']['nombre'] }}
        @endif
        <br>
        Fecha de corte: {{ \Carbon\Carbon::parse($reporte['fecha_corte'])->format('d/m/Y') }}
    </div>

    @if($esIndividual)
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Documento</th>
                    <th>Vencimiento</th>
                    <th>Días</th>
                    <th>Bucket</th>
                    <th>Saldo</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reporte['filas'] as $fila)
                    <tr>
                        <td>{{ $fila['fecha'] ? \Carbon\Carbon::parse($fila['fecha'])->format('d/m/Y') : '' }}</td>
                        <td class="text-left">{{ $fila['documento'] }}</td>
                        <td>{{ !empty($fila['fecha_pago']) ? \Carbon\Carbon::parse($fila['fecha_pago'])->format('d/m/Y') : '' }}</td>
                        <td class="text-right">{{ $fila['dias'] }}</td>
                        <td>{{ $fila['bucket_label'] }}</td>
                        <td class="text-right">{{ number_format($fila['saldo'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center">Sin saldos pendientes</td></tr>
                @endforelse
                @if(count($reporte['filas']))
                    <tr class="totales">
                        <td colspan="5" class="text-right">Total</td>
                        <td class="text-right">{{ number_format($reporte['totales']['total'] ?? 0, 2) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    @else
        <table>
            <thead>
                <tr>
                    <th class="text-left">{{ ($reporte['tipo'] ?? 'cxc') === 'cxp' ? 'Proveedor' : 'Cliente' }}</th>
                    <th>Identificación</th>
                    @foreach($activos as $b)
                        <th>{{ $labels[$b] ?? $b }}</th>
                    @endforeach
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reporte['filas'] as $fila)
                    <tr>
                        <td class="text-left">{{ $fila['nombre'] }}</td>
                        <td>{{ $fila['identificacion'] ?? '' }}</td>
                        @foreach($activos as $b)
                            <td class="text-right">{{ number_format($fila[$b] ?? 0, 2) }}</td>
                        @endforeach
                        <td class="text-right">{{ number_format($fila['total'] ?? 0, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 3 + count($activos) }}" style="text-align:center">Sin saldos pendientes</td></tr>
                @endforelse
                @if(count($reporte['filas']))
                    <tr class="totales">
                        <td class="text-left" colspan="2">TOTALES</td>
                        @foreach($activos as $b)
                            <td class="text-right">{{ number_format($reporte['totales'][$b] ?? 0, 2) }}</td>
                        @endforeach
                        <td class="text-right">{{ number_format($reporte['totales']['total'] ?? 0, 2) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    @endif
</body>
</html>
