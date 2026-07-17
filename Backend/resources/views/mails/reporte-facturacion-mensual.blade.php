<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SmartPyme — Resumen de Facturación Mensual</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f6f8;
            margin: 0;
            padding: 0;
            color: #333333;
        }
        .container {
            max-width: 650px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #e1e4e8;
        }
        .header {
            background: linear-gradient(135deg, #0056b3 0%, #007bff 100%);
            color: #ffffff;
            padding: 30px 20px;
            text-align: center;
        }
        .header img {
            max-width: 180px;
            margin-bottom: 12px;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .content {
            padding: 30px 25px;
        }
        .intro-text {
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 25px;
            color: #555555;
        }
        .kpi-container {
            display: table;
            width: 100%;
            margin-bottom: 30px;
            border-spacing: 10px 0;
        }
        .kpi-card {
            display: table-cell;
            width: 33.33%;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            vertical-align: middle;
        }
        .kpi-card.success {
            background-color: #eaf7ed;
            border: 1px solid #a3cfbb;
            color: #0f5132;
        }
        .kpi-card.danger {
            background-color: #fdf2f2;
            border: 1px solid #f5c2c2;
            color: #842029;
        }
        .kpi-card.neutral {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #495057;
        }
        .kpi-val {
            font-size: 26px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .kpi-lbl {
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        h2 {
            font-size: 16px;
            font-weight: 600;
            border-bottom: 2px solid #eaedf1;
            padding-bottom: 8px;
            margin-top: 30px;
            margin-bottom: 15px;
        }
        h2.error-title {
            color: #b02a37;
            border-bottom-color: #f5c2c2;
        }
        h2.success-title {
            color: #146c43;
            border-bottom-color: #a3cfbb;
        }
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            margin-bottom: 25px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .report-table th {
            font-weight: 600;
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        .report-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f3f5;
            color: #444444;
            vertical-align: top;
        }
        .report-table.success-table th {
            background-color: #eaf7ed;
            color: #0f5132;
        }
        .report-table.error-table th {
            background-color: #fdf2f2;
            color: #842029;
        }
        .badge {
            display: inline-block;
            padding: 3px 6px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 4px;
        }
        .badge-success {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        .badge-danger {
            background-color: #f8d7da;
            color: #842029;
        }
        .badge-secondary {
            background-color: #e2e3e5;
            color: #41464b;
        }
        .error-message {
            font-family: monospace;
            font-size: 11px;
            color: #b02a37;
            background-color: #fcf8f8;
            padding: 4px 6px;
            border-radius: 4px;
            display: inline-block;
            max-width: 100%;
            word-break: break-all;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 11px;
            color: #6c757d;
            border-top: 1px solid #eaedf1;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="https://www.smartpyme.sv/wp-content/uploads/2022/09/logo-web-smartpyme-2022-new.png" alt="SmartPyme Logo">
            <h1>Resumen de Facturación Mensual</h1>
        </div>
        
        <div class="content">
            <p class="intro-text">
                Se ha completado el proceso de generación automática de facturas y emisión de DTEs para las suscripciones activas del sistema. A continuación se detallan los resultados:
            </p>
            
            <div class="kpi-container">
                <div class="kpi-card success">
                    <div class="kpi-val">{{ $procesadas }}</div>
                    <div class="kpi-lbl">Emitidas OK</div>
                </div>
                <div class="kpi-card danger">
                    <div class="kpi-val">{{ $errores }}</div>
                    <div class="kpi-lbl">Fallidas</div>
                </div>
                <div class="kpi-card neutral">
                    <div class="kpi-val">{{ $tiempoTotal }}s</div>
                    <div class="kpi-lbl">Tiempo Ejecución</div>
                </div>
            </div>

            @if(count($fallidas) > 0)
                <h2 class="error-title">Suscripciones con Error en Facturación ({{ count($fallidas) }})</h2>
                <div class="table-responsive">
                    <table class="report-table error-table">
                        <thead>
                            <tr>
                                <th style="width: 25%;">Empresa</th>
                                <th style="width: 15%;">Suscripción</th>
                                <th style="width: 15%;">Plan</th>
                                <th style="width: 45%;">Detalle del Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($fallidas as $fail)
                                <tr>
                                    <td>
                                        <strong>{{ $fail['empresa_nombre'] }}</strong><br>
                                        <span style="color:#777; font-size:11px;">ID: {{ $fail['empresa_id'] }}</span>
                                    </td>
                                    <td>#{{ $fail['suscripcion_id'] }}</td>
                                    <td><span class="badge badge-secondary">{{ $fail['tipo_plan'] ?? 'N/A' }}</span></td>
                                    <td>
                                        <span class="error-message">{{ $fail['error'] }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if(count($exitosas) > 0)
                <h2 class="success-title">Suscripciones Emitidas Exitosamente ({{ count($exitosas) }})</h2>
                <div class="table-responsive">
                    <table class="report-table success-table">
                        <thead>
                            <tr>
                                <th style="width: 30%;">Empresa</th>
                                <th style="width: 15%;">Suscripción</th>
                                <th style="width: 15%;">Venta ID</th>
                                <th style="width: 20%;">Plan</th>
                                <th style="width: 20%; text-align: right;">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($exitosas as $ok)
                                <tr>
                                    <td>
                                        <strong>{{ $ok['empresa_nombre'] }}</strong><br>
                                        <span style="color:#777; font-size:11px;">ID: {{ $ok['empresa_id'] }}</span>
                                    </td>
                                    <td>#{{ $ok['suscripcion_id'] }}</td>
                                    <td>
                                        @if(!empty($ok['venta_id']))
                                            <span class="badge badge-success">Venta #{{ $ok['venta_id'] }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td><span class="badge badge-secondary">{{ $ok['tipo_plan'] ?? 'N/A' }}</span></td>
                                    <td style="text-align: right; font-weight: 600;">${{ number_format($ok['monto'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        
        <div class="footer">
            <p>Este es un reporte automático generado el {{ $generado }} por el sistema de Facturación de Suscripciones.</p>
            <p>&copy; {{ date('Y') }} SmartPyme. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
