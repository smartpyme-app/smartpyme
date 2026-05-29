<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SmartPyme — Reporte interno</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #333; line-height: 1.5;">
    <p>{{ $intro }}</p>
    <p style="color: #666; font-size: 12px;">Generado: {{ $generado }}</p>

    @if(empty($filas))
        <p><strong>No hay registros en esta categoría.</strong></p>
    @else
        <table style="border-collapse: collapse; width: 100%; max-width: 900px; margin-top: 16px; font-size: 13px;">
            <thead>
                <tr style="background: #f0f0f0;">
                    <th style="border: 1px solid #ccc; padding: 8px; text-align: left;">Empresa</th>
                    <th style="border: 1px solid #ccc; padding: 8px; text-align: left;">Fecha próximo pago</th>
                    <th style="border: 1px solid #ccc; padding: 8px; text-align: left;">Estado</th>
                    <th style="border: 1px solid #ccc; padding: 8px; text-align: left;">Plan</th>
                    <th style="border: 1px solid #ccc; padding: 8px; text-align: left;">Contacto (email)</th>
                    <th style="border: 1px solid #ccc; padding: 8px; text-align: left;">Días (calculado)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($filas as $fila)
                    <tr>
                        <td style="border: 1px solid #ccc; padding: 8px;">{{ $fila['empresa'] }}</td>
                        <td style="border: 1px solid #ccc; padding: 8px;">{{ $fila['fecha_proximo_pago'] }}</td>
                        <td style="border: 1px solid #ccc; padding: 8px;">{{ $fila['estado'] }}</td>
                        <td style="border: 1px solid #ccc; padding: 8px;">{{ $fila['plan'] }}</td>
                        <td style="border: 1px solid #ccc; padding: 8px;">{{ $fila['contacto'] }}</td>
                        <td style="border: 1px solid #ccc; padding: 8px;">{{ $fila['dias_faltantes'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p style="margin-top: 24px; font-size: 12px; color: #888;">SmartPyme — uso interno</p>
</body>
</html>
