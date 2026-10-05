<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SmartPyme — Ventas recurrentes</title>
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
            padding: 28px 20px;
            text-align: center;
        }
        .header img {
            max-width: 180px;
            margin-bottom: 12px;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        .header p {
            margin: 8px 0 0;
            font-size: 13px;
            opacity: 0.95;
        }
        .content {
            padding: 28px 24px;
        }
        .intro-text {
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 22px;
            color: #555555;
        }
        .kpi-container {
            display: table;
            width: 100%;
            margin-bottom: 26px;
            border-spacing: 10px 0;
        }
        .kpi-card {
            display: table-cell;
            width: 50%;
            padding: 14px;
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
        .kpi-val {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .kpi-lbl {
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        h2 {
            font-size: 15px;
            font-weight: 600;
            border-bottom: 2px solid #eaedf1;
            padding-bottom: 8px;
            margin-top: 24px;
            margin-bottom: 12px;
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
            margin-bottom: 20px;
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
        .line-ok {
            color: #0f5132;
        }
        .line-error {
            font-size: 12px;
            color: #842029;
            word-break: break-word;
        }
        .empty-state {
            padding: 16px;
            text-align: center;
            color: #6c757d;
            font-size: 14px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px dashed #dee2e6;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 18px;
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
            <img src="https://www.smartpyme.sv/wp-content/uploads/2022/09/logo-web-smartpyme-2022-new.png" alt="SmartPyme">
            <h1>Resumen de ventas recurrentes</h1>
            <p>{{ $empresaNombre }} · {{ $fechaEtiqueta }}</p>
        </div>

        <div class="content">
            <p class="intro-text">
                El proceso automático de ventas recurrentes finalizó para la fecha indicada.
                Revise el detalle de ventas emitidas y de las que requieren acción manual.
            </p>

            <div class="kpi-container">
                <div class="kpi-card success">
                    <div class="kpi-val">{{ count($emitidas) }}</div>
                    <div class="kpi-lbl">Emitidas / OK</div>
                </div>
                <div class="kpi-card danger">
                    <div class="kpi-val">{{ count($fallidas) }}</div>
                    <div class="kpi-lbl">Con error</div>
                </div>
            </div>

            @if(count($emitidas) > 0)
                <h2 class="success-title">Ventas procesadas correctamente ({{ count($emitidas) }})</h2>
                <div class="table-responsive">
                    <table class="report-table success-table">
                        <thead>
                            <tr>
                                <th style="width: 100%;">Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($emitidas as $linea)
                                <tr>
                                    <td class="line-ok">{{ $linea }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="empty-state">No hubo ventas emitidas en esta ejecución.</p>
            @endif

            @if(count($fallidas) > 0)
                <h2 class="error-title">Requieren revisión ({{ count($fallidas) }})</h2>
                <div class="table-responsive">
                    <table class="report-table error-table">
                        <thead>
                            <tr>
                                <th style="width: 100%;">Detalle del error o pendiente</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($fallidas as $linea)
                                <tr>
                                    <td class="line-error">{{ $linea }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="footer">
            <p>Reporte generado el {{ $generado }} (hora El Salvador).</p>
            <p>&copy; {{ date('Y') }} SmartPyme. Correo automático; no responda a este mensaje.</p>
        </div>
    </div>
</body>
</html>
