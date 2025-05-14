<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Financiero - {{ $datos['fecha'] }}</title>
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
            max-width: 650px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #f3f3f3;
            margin-bottom: 25px;
        }
        .logo {
            margin-bottom: 15px;
            max-width: 150px;
            height: auto;
        }
        .header h1 {
            color: #2c3e50;
            margin: 0;
            font-size: 26px;
            font-weight: 600;
        }
        .header p {
            color: #7f8c8d;
            margin: 5px 0 0;
            font-size: 14px;
        }
        .summary {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 25px;
            border-left: 4px solid #3498db;
        }
        .summary h2 {
            margin-top: 0;
            color: #2c3e50;
            font-size: 18px;
            font-weight: 600;
        }
        .summary-item {
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
        }
        .summary-label {
            font-weight: 600;
            flex: 1;
        }
        .summary-value {
            text-align: right;
            flex: 1;
        }
        .period {
            background-color: #e8f4fd;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: 500;
            color: #2573b7;
            display: inline-block;
        }
        .message {
            margin-bottom: 25px;
            color: #505050;
        }
        .note {
            background-color: #fff8e1;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            font-size: 14px;
            border-left: 4px solid #ffc107;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #f3f3f3;
            font-size: 13px;
            color: #95a5a6;
        }
        .support {
            background-color: #f3f3f3;
            padding: 15px;
            border-radius: 4px;
            margin-top: 30px;
            text-align: center;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
            margin-top: 20px;
        }
        .button:hover {
            background-color: #2980b9;
        }
        
        /* Estilos específicos para distintos tipos de reporte */
        .estado-financiero {
            border-left-color: #27ae60;
        }
        .ventas-categoria {
            border-left-color: #9b59b6;
        }
        
        @media only screen and (max-width: 600px) {
            .container {
                padding: 20px;
            }
            .summary-item {
                flex-direction: column;
            }
            .summary-value {
                text-align: left;
                margin-top: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <!-- Si tienes un logo, descomenta esta línea y coloca la ruta correcta -->
            <!-- <img src="{{ asset('img/logo.png') }}" alt="Logo de la empresa" class="logo"> -->
            
            @if($datos['tipo_reporte'] === 'ventas-por-vendedor')
                <h1>Reporte de Ventas por Vendedor</h1>
            @elseif($datos['tipo_reporte'] === 'ventas-por-categoria-vendedor')
                <h1>Reporte de Ventas por Categoría y Vendedor</h1>
            @elseif($datos['tipo_reporte'] === 'estado-financiero-consolidado-sucursales')
                <h1>Estado Financiero Consolidado por Sucursales</h1>
            @elseif($datos['tipo_reporte'] === 'detalle-ventas-vendedor')
                <h1>Detalle de Ventas por Vendedor</h1>
            @else
                <h1>Reporte Financiero</h1>
            @endif
            
            <p>Generado automáticamente el {{ date('d/m/Y H:i') }}</p>
        </div>
        
        <div class="message">
            <p>Estimado/a usuario,</p>
            
            <div class="period">
                <i class="far fa-calendar-alt"></i> Período: <strong>{{ $datos['fecha_inicio'] ?? $datos['fecha'] }} al {{ $datos['fecha_fin'] ?? $datos['fecha'] }}</strong>
            </div>
            
            @if($datos['tipo_reporte'] === 'ventas-por-vendedor')
                <p>Adjunto encontrará el reporte detallado de ventas por vendedor para el período seleccionado.</p>
                
                <div class="summary">
                    <h2>Resumen del reporte:</h2>
                    <div class="summary-item">
                        <span class="summary-label">Total de ventas realizadas:</span>
                        <span class="summary-value">{{ $datos['ventasDelDia'] }}</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Monto total:</span>
                        <span class="summary-value">${{ number_format($datos['totalVentas'], 2) }}</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Vendedores con ventas:</span>
                        <span class="summary-value">{{ $datos['vendedoresConVentas'] }}</span>
                    </div>
                </div>
                
                <p>El archivo adjunto contiene el detalle completo de todas las ventas realizadas por cada vendedor, incluyendo información sobre los productos vendidos, clientes, formas de pago y totales.</p>
                
            @elseif($datos['tipo_reporte'] === 'ventas-por-categoria-vendedor')
                <p>Adjunto encontrará el reporte detallado de ventas por categoría y vendedor para el período seleccionado.</p>
                
                <div class="summary ventas-categoria">
                    <h2>Resumen del reporte:</h2>
                    <!-- Si tienes datos específicos para este tipo de reporte, agrégalos aquí -->
                    <p>Este reporte muestra las ventas realizadas por cada vendedor, clasificadas por categoría de productos, con los porcentajes aplicados según la configuración establecida.</p>
                </div>
                
            @elseif($datos['tipo_reporte'] === 'estado-financiero-consolidado-sucursales')
                <p>Adjunto encontrará el estado financiero consolidado por sucursales para el período seleccionado.</p>
                
                <div class="summary estado-financiero">
                    <h2>Resumen del reporte:</h2>
                    <!-- Si tienes datos específicos para este tipo de reporte, agrégalos aquí -->
                    <p>Este reporte proporciona una visión global del desempeño financiero de cada sucursal, mostrando ventas, gastos y el balance resultante durante el período especificado.</p>
                </div>
                
            @elseif($datos['tipo_reporte'] === 'detalle-ventas-vendedor')
                <p>Adjunto encontrará el detalle de ventas por vendedor para el período seleccionado.</p>
                
                <div class="summary detalle-ventas">
                    <h2>Resumen del reporte:</h2>
                    <!-- Si tienes datos específicos para este tipo de reporte, agrégalos aquí -->
                    <p>Este reporte muestra el detalle de ventas realizadas por cada vendedor, incluyendo información sobre los productos vendidos, clientes, formas de pago y totales.</p>
                </div>
                
            @endif
        </div>

        <div class="note">
            <strong>Nota:</strong> Este reporte es generado automáticamente y se envía según la programación establecida. La información presentada es confidencial y para uso interno de la empresa.
        </div>
        <div class="support">
            <strong>¿Necesita ayuda?</strong><br>
            Contacte a nuestro equipo de soporte al <strong>+503 7732 5932</strong><br>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ $datos['empresa'] }}</p>
            <p>Este correo y cualquier archivo adjunto son confidenciales y para uso exclusivo del destinatario previsto.</p>
        </div>
    </div>
</body>
</html>