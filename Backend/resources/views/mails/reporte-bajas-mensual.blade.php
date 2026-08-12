<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SmartPyme — Bajas de suscripción</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #333; line-height: 1.5;">
    <h2 style="margin: 0 0 8px; font-size: 18px;">Reporte de bajas de suscripción</h2>
    <p style="margin: 0 0 4px;">Mes de referencia: <strong>{{ $mesEtiqueta }}</strong></p>
    <p style="color: #666; font-size: 12px; margin: 0 0 20px;">Generado: {{ $generado }}</p>

    <h3 style="font-size: 15px; margin: 0 0 8px;">Resumen del mes</h3>
    <table style="border-collapse: collapse; width: 100%; max-width: 720px; font-size: 13px; margin-bottom: 20px;">
        <tr>
            <td style="border: 1px solid #ccc; padding: 8px;">Total bajas</td>
            <td style="border: 1px solid #ccc; padding: 8px;"><strong>{{ $resumen['total'] }}</strong></td>
        </tr>
        <tr>
            <td style="border: 1px solid #ccc; padding: 8px;">Mensuales (USD)</td>
            <td style="border: 1px solid #ccc; padding: 8px;">${{ number_format($resumen['mensuales'], 2) }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #ccc; padding: 8px;">Trimestrales (USD)</td>
            <td style="border: 1px solid #ccc; padding: 8px;">${{ number_format($resumen['trimestrales'], 2) }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #ccc; padding: 8px;">Anuales (USD)</td>
            <td style="border: 1px solid #ccc; padding: 8px;">${{ number_format($resumen['anuales'], 2) }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #ccc; padding: 8px;">Por motivo</td>
            <td style="border: 1px solid #ccc; padding: 8px;">
                Voluntaria: {{ $resumen['por_motivo']['cancelacion_voluntaria'] ?? 0 }}
                · Falta de pago: {{ $resumen['por_motivo']['falta_pago'] ?? 0 }}
                · Inactividad: {{ $resumen['por_motivo']['inactividad'] ?? 0 }}
            </td>
        </tr>
    </table>

    <h3 style="font-size: 15px; margin: 0 0 8px;">Histórico (últimos 12 meses)</h3>
    <table style="border-collapse: collapse; width: 100%; max-width: 900px; font-size: 13px; margin-bottom: 20px;">
        <thead>
            <tr style="background: #f0f0f0;">
                <th style="border: 1px solid #ccc; padding: 8px; text-align: left;">Mes</th>
                <th style="border: 1px solid #ccc; padding: 8px; text-align: right;">Bajas</th>
                <th style="border: 1px solid #ccc; padding: 8px; text-align: right;">Mensuales</th>
                <th style="border: 1px solid #ccc; padding: 8px; text-align: right;">Trimestrales</th>
                <th style="border: 1px solid #ccc; padding: 8px; text-align: right;">Anuales</th>
            </tr>
        </thead>
        <tbody>
            @foreach($historico as $fila)
                <tr>
                    <td style="border: 1px solid #ccc; padding: 8px;">{{ $fila['etiqueta'] }}</td>
                    <td style="border: 1px solid #ccc; padding: 8px; text-align: right;">{{ $fila['total'] }}</td>
                    <td style="border: 1px solid #ccc; padding: 8px; text-align: right;">${{ number_format($fila['mensuales'], 2) }}</td>
                    <td style="border: 1px solid #ccc; padding: 8px; text-align: right;">${{ number_format($fila['trimestrales'], 2) }}</td>
                    <td style="border: 1px solid #ccc; padding: 8px; text-align: right;">${{ number_format($fila['anuales'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h3 style="font-size: 15px; margin: 0 0 8px;">Proyección resto del año {{ $anio }}</h3>
    <p style="font-size: 13px; margin: 0 0 10px;">
        Impacto orientativo de las bajas YTD (hasta {{ $mesEtiqueta }}) sobre los meses restantes:
    </p>
    <table style="border-collapse: collapse; width: 100%; max-width: 720px; font-size: 13px; margin-bottom: 12px;">
        <tr>
            <td style="border: 1px solid #ccc; padding: 8px;">MRR mensual perdido YTD</td>
            <td style="border: 1px solid #ccc; padding: 8px;">${{ number_format($proyeccion['mrr_mensual_ytd'], 2) }}</td>
        </tr>
        <tr>
            <td style="border: 1px solid #ccc; padding: 8px;">Meses restantes × MRR</td>
            <td style="border: 1px solid #ccc; padding: 8px;">
                {{ $proyeccion['meses_restantes'] }} × ${{ number_format($proyeccion['mrr_mensual_ytd'], 2) }}
                = <strong>${{ number_format($proyeccion['impacto_mensual_restante'], 2) }}</strong>
            </td>
        </tr>
        <tr>
            <td style="border: 1px solid #ccc; padding: 8px;">Trimestrales YTD / impacto ciclos restantes</td>
            <td style="border: 1px solid #ccc; padding: 8px;">
                ${{ number_format($proyeccion['trimestral_ytd'], 2) }}
                → ${{ number_format($proyeccion['impacto_trimestral_restante'], 2) }}
            </td>
        </tr>
        <tr>
            <td style="border: 1px solid #ccc; padding: 8px;">ARR anual perdido YTD (sin multiplicar)</td>
            <td style="border: 1px solid #ccc; padding: 8px;">${{ number_format($proyeccion['anual_ytd'], 2) }}</td>
        </tr>
        <tr style="background: #f8f8f8;">
            <td style="border: 1px solid #ccc; padding: 8px;"><strong>Total orientativo resto del año</strong></td>
            <td style="border: 1px solid #ccc; padding: 8px;"><strong>${{ number_format($proyeccion['total_orientativo'], 2) }}</strong></td>
        </tr>
    </table>

    @if(!empty($proyeccion['filas_meses']))
        <table style="border-collapse: collapse; width: 100%; max-width: 720px; font-size: 13px; margin-bottom: 20px;">
            <thead>
                <tr style="background: #f0f0f0;">
                    <th style="border: 1px solid #ccc; padding: 8px; text-align: left;">Mes proyectado</th>
                    <th style="border: 1px solid #ccc; padding: 8px; text-align: right;">MRR mensual perdido</th>
                </tr>
            </thead>
            <tbody>
                @foreach($proyeccion['filas_meses'] as $fila)
                    <tr>
                        <td style="border: 1px solid #ccc; padding: 8px;">{{ $fila['etiqueta'] }}</td>
                        <td style="border: 1px solid #ccc; padding: 8px; text-align: right;">${{ number_format($fila['mensuales'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="font-size: 13px; color: #666;">No hay meses restantes en el año calendario para proyectar.</p>
    @endif

    <p style="font-size: 13px;">Adjunto encontrará el Excel con el detalle de empresas del mes, histórico 12 meses y proyección.</p>
    <p style="margin-top: 24px; font-size: 12px; color: #888;">SmartPyme — uso interno</p>
</body>
</html>
