<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Puntos Ganados - {{ $empresa->nombre }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            max-width: 150px;
            height: auto;
        }
        .empresa-nombre {
            color: #007bff;
            font-size: 24px;
            font-weight: bold;
            margin-top: 10px;
        }
        .puntos-container {
            background: white;
            color: #007bff;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
        }
        .puntos-ganados {
            font-size: 36px;
            font-weight: bold;
            margin: 10px 0;
        }
        .puntos-disponibles {
            font-size: 18px;
            opacity: 0.9;
        }
        .venta-info {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: bold;
            color: #495057;
        }
        .info-value {
            color: #6c757d;
        }
        .mensaje {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            color: #6c757d;
            font-size: 14px;
        }
        .btn {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 5px;
            margin: 15px 0;
            font-weight: bold;
        }
        .btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            @if($empresa->logo && $empresa->logo !== 'empresas/default.jpg')
                <img src="{{ asset('storage/' . $empresa->logo) }}" alt="{{ $empresa->nombre }}" class="logo">
            @endif
            <div class="empresa-nombre">{{ $empresa->nombre }}</div>
        </div>

        <h2>¡Felicitaciones {{ $cliente->nombre }}!</h2>
        
        <p>Has realizado una compra y ganado puntos en nuestro programa de fidelización.</p>

        <div class="puntos-container">
            <div>¡Has ganado!</div>
            <div class="puntos-ganados">{{ number_format($puntos_ganados) }} puntos</div>
            <div class="puntos-disponibles">
                Puntos disponibles: {{ number_format($puntos_disponibles) }}
            </div>
        </div>

        <div class="mensaje">
            <strong>¡Gracias por tu compra!</strong><br>
            Tus puntos han sido agregados a tu cuenta y ya están disponibles para canjear.
        </div>

        <div class="venta-info">
            <h3>Detalles de tu compra:</h3>
            <div class="info-row">
                <span class="info-label">Número de venta:</span>
                <span class="info-value">#{{ $numero_venta }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Fecha:</span>
                <span class="info-value">{{ $fecha_venta->format('d/m/Y H:i') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Total:</span>
                <span class="info-value">${{ number_format($venta->total, 2) }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Puntos ganados:</span>
                <span class="info-value">{{ number_format($puntos_ganados) }} puntos</span>
            </div>
        </div>

        <div class="footer">
            <p>Este es un mensaje automático del sistema de fidelización de {{ $empresa->nombre }}.</p>
            <p>Si tienes alguna pregunta, no dudes en contactarnos.</p>
            @if($empresa->correo)
                <p>Email: {{ $empresa->correo }}</p>
            @endif
            @if($empresa->telefono)
                <p>Teléfono: {{ $empresa->telefono }}</p>
            @endif
        </div>
    </div>
</body>
</html>
