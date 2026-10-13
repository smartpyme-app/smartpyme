<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitación despacho contable — SmartPyme</title>
</head>
<body style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;color:#334155;background-color:#eef2f7;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#eef2f7;padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(15,23,42,0.08);">
                <tr>
                    <td style="background:#ffffff;padding:28px 32px 20px;text-align:center;border-bottom:1px solid #e2e8f0;">
                        <img width="150" src="https://www.smartpyme.sv/wp-content/uploads/2022/09/logo-web-smartpyme-2022-new.png" alt="SmartPyme" style="display:block;margin:0 auto 14px;">
                        <p style="margin:0;color:#1775e5;font-size:14px;font-weight:600;letter-spacing:0.04em;">Portal de contadores</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        <p style="margin:0 0 8px;font-size:13px;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;">Invitación de gestión</p>
                        <h1 style="margin:0 0 20px;font-size:22px;line-height:1.35;color:#0f172a;font-weight:700;">
                            {{ $empresaDespacho->nombre }} quiere administrar tu empresa en SmartPyme
                        </h1>
                        <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#475569;">
                            Hola <strong>{{ $nombreAdmin }}</strong>,
                        </p>
                        <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#475569;">
                            El despacho contable <strong>{{ $empresaDespacho->nombre }}</strong> te invita a autorizar la gestión de
                            <strong>{{ $empresaCliente->nombre }}</strong> desde su portal de contadores en SmartPyme.
                        </p>
                        <ul style="margin:0 0 24px;padding:0 0 0 18px;font-size:14px;line-height:1.7;color:#475569;">
                            <li>Cumplimiento y calendario fiscal de tu empresa</li>
                            <li>Documentos y obligaciones del periodo</li>
                            <li>Avance de contabilización (ventas, compras y gastos)</li>
                        </ul>
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 28px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;">
                            <tr>
                                <td style="padding:16px 20px;">
                                    <p style="margin:0 0 6px;font-size:12px;color:#64748b;">Tu empresa</p>
                                    <p style="margin:0;font-size:16px;font-weight:600;color:#0f172a;">{{ $empresaCliente->nombre }}</p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:0 20px 16px;border-top:1px solid #e2e8f0;">
                                    <p style="margin:12px 0 6px;font-size:12px;color:#64748b;">Despacho</p>
                                    <p style="margin:0;font-size:16px;font-weight:600;color:#0f172a;">{{ $empresaDespacho->nombre }}</p>
                                </td>
                            </tr>
                        </table>
                        <table role="presentation" cellspacing="0" cellpadding="0" align="center" style="margin:0 auto 20px;">
                            <tr>
                                <td style="border-radius:8px;background:#1775e5;box-shadow:0 4px 14px rgba(23,117,229,0.35);">
                                    <a href="{{ $aceptarUrl }}" target="_blank" style="display:inline-block;padding:14px 32px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:8px;">
                                        Revisar y aceptar invitación
                                    </a>
                                </td>
                            </tr>
                        </table>
                        <p style="margin:0 0 24px;font-size:12px;line-height:1.5;color:#94a3b8;text-align:center;word-break:break-all;">
                            Enlace directo:<br>
                            <a href="{{ $aceptarUrl }}" style="color:#1775e5;text-decoration:none;">{{ $aceptarUrl }}</a>
                        </p>
                        <p style="margin:0 0 12px;font-size:13px;line-height:1.5;color:#64748b;text-align:center;">
                            También puedes aceptar o rechazar desde <strong>Cuenta → Despacho contable</strong> en SmartPyme, iniciando sesión como administrador.
                        </p>
                        <p style="margin:0;font-size:12px;line-height:1.5;color:#94a3b8;text-align:center;">
                            Esta invitación vence el {{ $expiraEnTexto }}. Si no reconoces esta solicitud, ignora el correo.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 32px;background:#f8fafc;border-top:1px solid #e2e8f0;text-align:center;">
                        <p style="margin:0 0 4px;font-size:12px;color:#64748b;">SmartPyme &copy; {{ date('Y') }}</p>
                        <p style="margin:0;font-size:12px;color:#94a3b8;">
                            <a href="https://smartpyme.sv" style="color:#1775e5;text-decoration:none;">smartpyme.sv</a>
                            · contact@smartpyme.sv
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
