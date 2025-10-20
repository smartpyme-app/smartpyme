<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartPYME External API - Documentación</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@4.15.5/swagger-ui.css" />
    <link rel="icon" type="image/png" href="https://unpkg.com/swagger-ui-dist@4.15.5/favicon-32x32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="https://unpkg.com/swagger-ui-dist@4.15.5/favicon-16x16.png" sizes="16x16" />
    <style>
        html {
            box-sizing: border-box;
            overflow: -moz-scrollbars-vertical;
            overflow-y: scroll;
        }
        *, *:before, *:after {
            box-sizing: inherit;
        }
        body {
            margin: 0;
            background: #fafafa;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .swagger-ui .topbar {
            background-color: #2c3e50;
            padding: 10px 0;
        }
        .swagger-ui .topbar .download-url-wrapper .select-label {
            color: #fff;
        }
        .swagger-ui .topbar .download-url-wrapper input[type=text] {
            border: 2px solid #34495e;
        }
        .swagger-ui .info {
            margin: 50px 0;
        }
        .swagger-ui .info .title {
            font-size: 36px;
            color: #2c3e50;
        }
        .custom-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .custom-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 300;
        }
        .custom-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="custom-header">
        <h1>🚀 SmartPYME External API</h1>
        <p>Documentación interactiva para proveedores externos</p>
    </div>
    
    <div id="swagger-ui"></div>
    
    <script src="https://unpkg.com/swagger-ui-dist@4.15.5/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@4.15.5/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            // Debug: mostrar la URL que se está usando
            const jsonUrl = window.location.origin + '/api/external/documentation/json';
            console.log('🔍 Cargando especificación desde:', jsonUrl);
            
            const ui = SwaggerUIBundle({
                url: jsonUrl,
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout",
                validatorUrl: null,
                tryItOutEnabled: true,
                supportedSubmitMethods: ['get', 'post', 'put', 'delete', 'patch'],
                docExpansion: 'list',
                operationsSorter: 'alpha',
                defaultModelsExpandDepth: 1,
                defaultModelExpandDepth: 1,
                showExtensions: true,
                showCommonExtensions: true,
                onComplete: function() {
                    console.log('✅ SmartPYME External API Documentation cargada exitosamente');
                    
                    // Agregar información adicional
                    const infoSection = document.querySelector('.swagger-ui .info');
                    if (infoSection) {
                        const additionalInfo = document.createElement('div');
                        additionalInfo.innerHTML = `
                            <div style="background: #e8f4fd; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0; border-radius: 4px;">
                                <h4 style="margin: 0 0 10px 0; color: #1976D2;">🔐 Cómo usar esta API:</h4>
                                <ol style="margin: 0; padding-left: 20px;">
                                    <li>Haz clic en el botón <strong>"Authorize"</strong> (🔒) arriba</li>
                                    <li>Ingresa tu API Key en el formato: <code>tu_api_key_aqui</code></li>
                                    <li>Haz clic en <strong>"Authorize"</strong> y luego <strong>"Close"</strong></li>
                                    <li>Ahora puedes probar cualquier endpoint con <strong>"Try it out"</strong></li>
                                </ol>
                            </div>
                            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
                                <h4 style="margin: 0 0 10px 0; color: #856404;">⚡ Rate Limiting:</h4>
                                <p style="margin: 0;">• Sin filtros de fecha: <strong>100 requests/hora</strong><br>
                                • Con filtros de fecha: <strong>200 requests/hora</strong></p>
                            </div>
                        `;
                        infoSection.appendChild(additionalInfo);
                    }
                },
                onFailure: function(error) {
                    console.error('❌ Error cargando la especificación:', error);
                    document.getElementById('swagger-ui').innerHTML = `
                        <div style="text-align: center; padding: 50px;">
                            <h2>❌ Error cargando la documentación</h2>
                            <p>No se pudo cargar la especificación desde: <code>${jsonUrl}</code></p>
                            <p>Por favor verifica que el servidor esté funcionando correctamente.</p>
                            <button onclick="location.reload()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                🔄 Reintentar
                            </button>
                        </div>
                    `;
                },
                requestInterceptor: function(request) {
                    // Agregar el prefijo correcto a las URLs
                    if (request.url && !request.url.startsWith('http')) {
                        request.url = window.location.origin + '/api/external/v1' + request.url;
                    }
                    return request;
                },
                responseInterceptor: function(response) {
                    // Log de respuestas para debugging
                    console.log('API Response:', response.status, response.url);
                    return response;
                }
            });

            // Personalizar el título de la página
            document.title = 'SmartPYME External API - Documentación';
        };
    </script>
</body>
</html>
