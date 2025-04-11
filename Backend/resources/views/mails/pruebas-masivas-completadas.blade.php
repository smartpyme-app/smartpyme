<!DOCTYPE html>
<html>
<head>
    <title>Pruebas Masivas MH Completadas</title>
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
            background-color: #0075E9;
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
        .stats {
            margin: 15px 0;
            border-collapse: collapse;
            width: 100%;
        }
        .stats th, .stats td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .stats th {
            background-color: #f2f2f2;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-success {
            color: #3c763d;
            background-color: #dff0d8;
            border-color: #d6e9c6;
        }
        .progress {
            height: 20px;
            margin-bottom: 20px;
            overflow: hidden;
            background-color: #f5f5f5;
            border-radius: 4px;
            box-shadow: inset 0 1px 2px rgba(0,0,0,.1);
        }
        .progress-bar {
            float: left;
            width: 0;
            height: 100%;
            font-size: 12px;
            line-height: 20px;
            color: #fff;
            text-align: center;
            background-color: #337ab7;
            box-shadow: inset 0 -1px 0 rgba(0,0,0,.15);
            transition: width .6s ease;
        }
        .progress-bar-success {
            background-color: #5cb85c;
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="text-align: center;">
            <img width="200px" src="https://www.smartpyme.sv/wp-content/uploads/2022/09/logo-web-smartpyme-2022-new.png" alt="Logo SmartPyme">
        </div>
        <div class="header">
           
            <h2>Pruebas Masivas MH Completadas</h2>
        </div>
        
        <div class="content">
            <p>Estimado usuario,</p>
            
            <p>El proceso de generación de pruebas masivas para el Ministerio de Hacienda ha finalizado con el siguiente resultado:</p>
            
            <div class="alert alert-success">
                <strong>Tipo de documento:</strong> {{ $tipoTexto ?? 'Documento Tributario Electrónico' }}<br>
                <strong>Cantidad solicitada:</strong> {{ $cantidad }}<br>
                <strong>Documentos generados con éxito:</strong> {{ $resultado['exitosos'] }}<br>
                <strong>Documentos fallidos:</strong> {{ $resultado['fallidos'] }}
            </div>
            
            @if(isset($resultado['detalles']) && count($resultado['detalles']) > 0)
                <h3>Detalles del proceso:</h3>
                <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
                    <ul>
                        @foreach($resultado['detalles'] as $detalle)
                            <li>
                                <strong>Correlativo {{ $detalle['correlativo'] }}:</strong> 
                                {{ $detalle['status'] }} - {{ $detalle['message'] }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            
            {{-- <p>Para visualizar los resultados completos, por favor ingrese al sistema y visite el módulo de Facturación Electrónica.</p> --}}
        </div>
        
        <div class="footer">
            <p>Este es un mensaje automático, por favor no responda a este correo.</p>
        </div>
    </div>
</body>
</html>