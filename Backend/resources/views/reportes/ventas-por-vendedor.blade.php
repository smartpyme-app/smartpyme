<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Ventas por Vendedor</title>
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
            text-align: center;
            padding-bottom: 10px;
            border-bottom: 2px solid #f3f3f3;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #2c3e50;
            margin: 0;
            font-size: 24px;
        }
        .summary {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .summary h2 {
            margin-top: 0;
            color: #2c3e50;
            font-size: 18px;
        }
        .summary-item {
            margin-bottom: 10px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #f3f3f3;
            font-size: 12px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Reporte de Ventas por Vendedor</h1>
        </div>
        
        <p>Estimado/a,</p>
        
        <p>Adjunto encontrará el reporte detallado de ventas por vendedor correspondiente al día <strong>{{ $datos['fecha'] }}</strong>.</p>
        
        <div class="summary">
            <h2>Resumen del día:</h2>
            <div class="summary-item">
                <strong>Fecha:</strong> {{ $datos['fecha'] }}
            </div>
            <div class="summary-item">
                <strong>Total de ventas realizadas:</strong> {{ $datos['ventasDelDia'] }}
            </div>
            <div class="summary-item">
                <strong>Monto total:</strong> ${{ number_format($datos['totalVentas'], 2) }}
            </div>
        </div>
        
        <p>El archivo adjunto contiene el detalle completo de todas las ventas realizadas por cada vendedor durante el día, que incluye información sobre los productos vendidos, clientes, formas de pago y totales.</p>
        
        <p>Este reporte es generado automáticamente. Si tiene alguna pregunta o requiere información adicional, no dude en contactar al departamento de sistemas.</p>
        
        <p>Saludos cordiales,</p>
    </div>
</body>
</html>