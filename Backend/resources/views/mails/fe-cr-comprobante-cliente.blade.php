<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="UTF-8">
    <title>Comprobante electrónico</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; background-color:#e8e8e8; color:#111;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#e8e8e8; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px; background:#ffffff; border-radius:4px; overflow:hidden;">
                <tr>
                    <td style="padding:28px 28px 8px; text-align:center;">
                        <p style="margin:0 0 12px; font-size:18px; color:#003366; font-weight:normal;">Saludos cordiales</p>
                        <p style="margin:0; font-size:16px; color:#111; font-weight:bold; text-transform:uppercase;">{{ $nombreDestinatario }}</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 24px 24px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; font-size:13px;">
                            @foreach ($filas as $i => $fila)
                                <tr style="background:{{ $i % 2 === 0 ? '#f0f0f0' : '#ffffff' }};">
                                    <td style="padding:10px 12px; vertical-align:top; width:42%; font-weight:bold; color:#111;">{{ $fila['etiqueta'] }}</td>
                                    <td style="padding:10px 12px; vertical-align:top; color:#111; word-break:break-all;">{!! nl2br(e($fila['valor'])) !!}</td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 24px 28px; text-align:center;">
                        <p style="margin:0; font-size:16px; color:#003366; font-weight:bold;">Muchas gracias.</p>
                    </td>
                </tr>
            </table>
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px; margin-top:16px;">
                <tr>
                    <td style="padding:12px; text-align:center; font-size:11px; color:#555;">
                        **** Este mensaje es generado por un sistema automático, agradecemos no responder este mensaje. ****
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 12px 24px; text-align:center; font-size:11px; color:#666;">
                        Generado por SmartPyME
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
