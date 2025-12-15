<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: sans-serif;">

<div style="padding: 15px;">
    
    <table style="width: 100%;">
        <tr>
            <td style="text-align: center;">
                <img width="150px" src="https://www.smartpyme.sv/wp-content/uploads/2022/09/logo-web-smartpyme-2022-new.png" alt="Logo SmartPyme">
                <div style="margin-top: 15px; border-bottom: 1px solid #cecece;">
            </td>
        </tr>
        <tr>
            <td style="text-align: center; padding-top: 10px; padding-bottom: 15px;">
                <h3 style="color: #4CAF50; margin: 0;">✅ Reporte de Ventas Generado</h3>
            </td>
        </tr>
        <tr>
            <td style="padding: 50px 25px; background-color: #e8f5e8; border-radius: 30px; margin: 15px 0px; border: 2px solid #4CAF50;">
                <h3 style="margin: 0px; color: #2E7D32;">¡Reporte Listo!</h3>
                
                <p style="margin: 15px 0px;">Se ha generado exitosamente el reporte de ventas.</p>
                
                <div style="background-color: #f1f8e9; border: 1px solid #4CAF50; border-radius: 10px; padding: 20px; margin: 20px 0;">
                    <h4 style="margin: 0 0 15px 0; color: #2E7D32;">📊 Información del Archivo</h4>
                    <ul style="margin: 0; padding-left: 20px;">
                        <li><strong>Archivo:</strong> {{ $fileName }}</li>
                        <li><strong>Formato:</strong> CSV (Compatible con Excel)</li>
                        <li><strong>Fecha de generación:</strong> {{ date('d/m/Y H:i:s') }}</li>
                    </ul>
                </div>
                
                <p style="margin: 15px 0px;">El archivo contiene todas las ventas con información completa de clientes, documentos, formas de pago y totales.</p>
                
                <h4 style="color: #2E7D32; margin: 20px 0 10px 0;">📋 Contenido del Reporte:</h4>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>Fecha de la venta</li>
                    <li>Información del cliente (nombre, teléfono, DUI, NIT, dirección)</li>
                    <li>Información del documento y correlativo</li>
                    <li>Forma de pago y banco</li>
                    <li>Estado y canal de venta</li>
                    <li>Costos, subtotales, descuentos e IVA</li>
                    <li>Utilidad y totales</li>
                    <li>Información del usuario y vendedor</li>
                </ul>
                
                <div style="background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 10px; padding: 15px; margin: 20px 0;">
                    <p style="margin: 0; color: #856404;"><strong>⚠️ Nota:</strong> Este archivo se eliminará automáticamente del servidor después de ser enviado por seguridad.</p>
                </div>
            </td>
        </tr>
    </table>
    
    <table style="width: 100%;">
        <tr>
            <td>
                <div style="border-bottom: 1px solid #cecece;"></div>
            </td>
        </tr>
        <tr>
            <td>
                <p style="margin: 15px 0px; text-align: center; color: gray;">
                    <br><br>
                    <p>Saludos,</p>
                    SmartPyme
                    <br><a href="https://smartpyme.site" target="blank">smartpyme.site</a>
                    <br><a href="wa.me/50377325932" target="blank">+503 7732-5932</a>
                    <p>San Salvador, El Salvador</p>
                    <br>
                    <small>Este correo fue generado automáticamente por SmartPyme</small>
                </p>
            </td>
        </tr>
    </table>

</div>

</body>
</html>

