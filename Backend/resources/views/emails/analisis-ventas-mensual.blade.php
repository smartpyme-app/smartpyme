<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: sans-serif;">
<div style="padding: 15px;">
    <h3 style="color: #4CAF50;">Reporte listo</h3>
    <p>Se generó el reporte de comportamiento anual / inventario vs ventas que solicitó.</p>
    <p><strong>Archivo:</strong> {{ $fileName }}</p>
    <p><strong>Fecha:</strong> {{ date('d/m/Y H:i:s') }}</p>
    <p>El Excel va adjunto a este correo.</p>
</div>
</body>
</html>
