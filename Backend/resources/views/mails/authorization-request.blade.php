<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Autorización</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: #007bff;
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .content {
            background: #f8f9fa;
            padding: 30px;
            border: 1px solid #dee2e6;
        }
        .authorization-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #007bff;
        }
        .code-highlight {
            background: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 18px;
            text-align: center;
            margin: 15px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 10px 5px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .btn-approve {
            background: #28a745;
            color: white;
        }
        .btn-reject {
            background: #dc3545;
            color: white;
        }
        .footer {
            background: #6c757d;
            color: white;
            padding: 15px;
            border-radius: 0 0 8px 8px;
            text-align: center;
            font-size: 12px;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #fcecb3;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔐 Solicitud de Autorización</h1>
        <p>Se requiere tu aprobación para una acción del sistema</p>
    </div>

    <div class="content">
        <p>Hola <strong>{{ $authorizer->name }}</strong>,</p>
        
        <p>Se ha solicitado una autorización que requiere tu aprobación:</p>

        <div class="authorization-card">
            <h3>{{ $type }}</h3>
            <p><strong>Descripción:</strong> {{ $description }}</p>
            <p><strong>Solicitado por:</strong> {{ $requester }}</p>
            <p><strong>Fecha límite:</strong> {{ $expires_at }}</p>
        </div>

        <div class="code-highlight">
            Código de Autorización: {{ $code }}
        </div>

        <div class="warning">
            <strong>⚠️ Importante:</strong> Para aprobar o rechazar esta solicitud, necesitarás ingresar tu código personal de autorización, tienes que tenerlo configurado en tu perfil.
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $approve_url }}" class="btn btn-approve">Ver Solicitud</a>
        </div>

        <hr>

        <h4>¿Cómo proceder?</h4>
        <ol>
            <li>Haz clic en "Ver Solicitud" para acceder al sistema</li>
            <li>Revisa los detalles de la autorización</li>
            <li>Verifica el código de autorización: <strong>{{ $code }}</strong></li>
            <li>Ingresa tu código personal de autorización</li>
            <li>Aprueba o rechaza la solicitud</li>
        </ol>
    </div>

    <div class="footer">
        <p>Este es un correo automático del sistema SmartPyme</p>
        <p>No respondas a este correo</p>
    </div>
</body>
</html>