@php
    $fmt = fn ($n) => number_format((float) $n, 2);
    $notasLista = $notas['notas'] ?? [];
    ksort($notasLista);
    $completitud = $notas['completitud'] ?? [];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; }
        .header { text-align: center; margin-bottom: 16px; }
        .header h1 { font-size: 14px; margin: 4px 0; }
        .header h2 { font-size: 12px; margin: 2px 0; font-weight: normal; }
        .nota { page-break-inside: avoid; margin-bottom: 14px; border-top: 1px solid #ccc; padding-top: 8px; }
        .nota h3 { font-size: 11px; margin: 0 0 6px; color: #0d47a1; }
        .meta { font-size: 9px; color: #555; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        td { padding: 2px 4px; vertical-align: top; }
        .amt { text-align: right; white-space: nowrap; }
        .texto { text-align: justify; line-height: 1.4; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 8px; }
        .completa { background: #c8e6c9; }
        .parcial { background: #fff9c4; }
        .pendiente { background: #ffcdd2; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $empresa->nombre }}</h1>
        <h2>NOTAS A LOS ESTADOS FINANCIEROS</h2>
        <p>Periodo del {{ $fecha_inicio }} al {{ $fecha_fin }} (USD)</p>
        <p>Completitud: {{ $completitud['porcentaje'] ?? 0 }}% — {{ $completitud['completas'] ?? 0 }}/{{ $completitud['total_notas'] ?? 0 }} notas completas</p>
    </div>

    @foreach($notasLista as $numero => $nota)
        @php
            $estado = $nota['estado'] ?? 'PENDIENTE';
            $contenido = $nota['contenido'] ?? [];
            $badgeClass = match($estado) {
                'COMPLETA' => 'completa',
                'PARCIAL' => 'parcial',
                default => 'pendiente',
            };
        @endphp
        <div class="nota">
            <h3>Nota {{ $numero }}. {{ $nota['titulo'] ?? '' }}</h3>
            <div class="meta">
                <span class="badge {{ $badgeClass }}">{{ $estado }}</span>
                <span>{{ $nota['tipo'] ?? '' }}</span>
            </div>

            @if(isset($contenido['texto']))
                <p class="texto">{{ $contenido['texto'] }}</p>
            @elseif($numero == 4 && isset($contenido['total_efectivo']))
                <table>
                    <tr><td>Total efectivo y equivalentes (Balance)</td><td class="amt">${{ $fmt($contenido['total_efectivo']) }}</td></tr>
                    <tr><td>Total módulo bancos</td><td class="amt">${{ $fmt($contenido['total_modulo_bancos'] ?? 0) }}</td></tr>
                </table>
                @if(!empty($contenido['cuentas']))
                    <table>
                        <tr><td><strong>Banco</strong></td><td><strong>Cuenta</strong></td><td class="amt"><strong>Saldo</strong></td></tr>
                        @foreach($contenido['cuentas'] as $c)
                            <tr>
                                <td>{{ $c['banco'] ?? '' }}</td>
                                <td>{{ $c['numero'] ?? '' }}</td>
                                <td class="amt">${{ $fmt($c['saldo_modulo_bancos'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            @elseif($numero == 5)
                <table>
                    <tr><td>CxC bruto</td><td class="amt">${{ $fmt($contenido['cuentas_por_cobrar_bruto'] ?? 0) }}</td></tr>
                    <tr><td>Provisión incobrables</td><td class="amt">${{ $fmt($contenido['provision_balance'] ?? 0) }}</td></tr>
                    <tr><td>Neto</td><td class="amt">${{ $fmt($contenido['neto'] ?? 0) }}</td></tr>
                </table>
            @elseif($numero == 10 && isset($contenido['conciliacion']))
                <table>
                    @foreach($contenido['conciliacion'] as $fila)
                        <tr><td>{{ $fila['concepto'] ?? '' }}</td><td class="amt">${{ $fmt($fila['monto'] ?? 0) }}</td></tr>
                    @endforeach
                </table>
            @else
                <p class="texto">{{ json_encode($contenido, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</p>
            @endif
        </div>
    @endforeach

    @if(!empty($notas['validaciones_cruzadas']))
        <div class="nota">
            <h3>Validaciones cruzadas</h3>
            <table>
                @foreach($notas['validaciones_cruzadas'] as $v)
                    <tr>
                        <td>{{ $v['descripcion'] ?? '' }}</td>
                        <td>{{ ($v['cuadra'] ?? false) ? 'OK' : 'DIF: $'.$fmt($v['diferencia'] ?? 0) }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif
</body>
</html>
