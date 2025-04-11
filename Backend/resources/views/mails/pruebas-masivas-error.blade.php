<!DOCTYPE html>
<html>
<head>
    <title>Error en Pruebas Masivas MH</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .header {
            background-color: #d9534f;
            color: white;
            padding: 10px;
            text-align: center;
            border-radius: 3px;
            margin-bottom: 20px;
        }
        .content {
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 3px;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #777;
        }
        .error-details {
            background-color: #f2dede;
            color: #a94442;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #ebccd1;
            border-radius: 3px;
            word-break: break-word;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Error en Pruebas Masivas MH</h2>
        </div>
        
        <div class="content">
            <p>Estimado usuario,</p>
            
            <p>Se ha producido un error durante el proceso de generación de pruebas masivas para el Ministerio de Hacienda:</p>
            
            <div class="error-details">
                <strong>Tipo de documento:</strong> {{ $tipoTexto ?? 'Documento Tributario Electrónico' }}<br>
                <strong>Cantidad solicitada:</strong> {{ $cantidad }}<br>
                <strong>Error:</strong> {{ $error }}
            </div>
            
            <h3>Posibles causas:</h3>
            <ul>
                <li>Problemas de conectividad con el servicio de facturación electrónica</li>
                <li>Credenciales de acceso incorrectas o vencidas</li>
                <li>Formato de datos no válido en el documento base</li>
                <li>Tiempo de respuesta excedido en el servicio del Ministerio de Hacienda</li>
                <li>Error en la configuración de certificados o parámetros de firma</li>
            </ul>
            
            <p>Recomendaciones:</p>
            <ol>
                <li>Verifique su conexión a internet</li>
                <li>Compruebe que sus credenciales de acceso al Ministerio de Hacienda sean correctas</li>
                <li>Asegúrese de que el documento base seleccionado es válido y está completo</li>
                <li>Intente nuevamente con una cantidad menor de documentos</li>
                <li>Si el problema persiste, contacte a soporte técnico</li>
            </ol>
            
            <p>Por favor, intente el proceso nuevamente. Si continúa recibiendo este error, contacte a nuestro equipo de soporte técnico para recibir asistencia.</p>
        </div>
        
        <div class="footer">
            <p>Este es un mensaje automático, por favor no responda a este correo.</p>
        </div>
    </div>
</body>
</html>