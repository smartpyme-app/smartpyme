<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código de verificación para WhatsApp</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f9f9f9;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            padding-bottom: 25px;
            border-bottom: 2px solid #f3f3f3;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #25D366;
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .header p {
            color: #7f8c8d;
            margin: 10px 0 0;
            font-size: 16px;
        }
        .verification-section {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 30px;
            border-radius: 12px;
            margin: 25px 0;
            text-align: center;
            border: 2px solid #25D366;
        }
        .verification-code {
            font-size: 36px;
            font-weight: bold;
            color: #25D366;
            letter-spacing: 8px;
            margin: 20px 0;
            padding: 15px 25px;
            background-color: white;
            border-radius: 8px;
            display: inline-block;
            border: 2px dashed #25D366;
        }
        .verification-label {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .expiration-notice {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
            color: #856404;
        }
        .security-note {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
            color: #155724;
        }
        .steps {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .step {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            font-size: 15px;
        }
        .step-number {
            background-color: #25D366;
            color: white;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-weight: bold;
            font-size: 14px;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 25px;
            border-top: 1px solid #f3f3f3;
            font-size: 13px;
            color: #95a5a6;
        }
        .support-info {
            background-color: #e8f4fd;
            padding: 20px;
            border-radius: 8px;
            margin-top: 25px;
            text-align: center;
            border-left: 4px solid #3498db;
        }
        @media only screen and (max-width: 600px) {
            .container {
                padding: 20px;
                margin: 10px;
            }
            .verification-code {
                font-size: 28px;
                letter-spacing: 4px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Verificación de WhatsApp</h1>
            <p>¡Hola <strong>{{ $datos['userName'] }}</strong>!</p>
        </div>
        
        <div style="margin: 25px 0; color: #2c3e50; font-size: 16px;">
            <p>Hemos recibido una solicitud para conectar tu cuenta de <strong>{{ config('app.name') }}</strong> con WhatsApp.</p>
            <p>Para completar la verificación, utiliza el siguiente código:</p>
        </div>

        <div class="verification-section">
            <div class="verification-label">Tu código de verificación es:</div>
            <div class="verification-code">{{ $datos['verificationCode'] }}</div>
        </div>

        <div class="expiration-notice">
            <strong>⏰ Importante:</strong> Este código expirará en <strong>10 minutos</strong> por motivos de seguridad.
        </div>

        <div class="steps">
            <h3 style="margin-top: 0; color: #2c3e50;">Cómo usar este código:</h3>
            <div class="step">
                <span>Regresa a WhatsApp donde iniciaste el proceso</span>
            </div>
            <div class="step">
                <span>Escribe exactamente el código de 6 dígitos mostrado arriba</span>
            </div>
            <div class="step">
                <span>¡Listo! Tu cuenta quedará verificada y conectada</span>
            </div>
        </div>

        <div class="security-note">
            <strong>🔒 Nota de seguridad:</strong> Si no solicitaste este código, puedes ignorar este mensaje. Tu cuenta permanecerá segura.
        </div>
        
        <div class="footer">
            <p><strong>{{ config('app.name') }}</strong> - Tu asistente financiero inteligente</p>
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.</p>
            <p style="margin-top: 15px; font-size: 12px;">
                Este correo es confidencial y está destinado únicamente para el uso del destinatario previsto.
            </p>
        </div>
    </div>
</body>
</html>