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
                <h3 style="color: #f44336; margin: 0;">⚠️ Error al Generar Reporte</h3>
            </td>
        </tr>
        <tr>
            <td style="padding: 50px 25px; background-color: #ffebee; border-radius: 30px; margin: 15px 0px; border: 2px solid #f44336;">
                <h3 style="margin: 0px; color: #c62828;">Lo sentimos</h3>
                
                <p style="margin: 15px 0px;">Ha ocurrido un error al intentar generar el reporte de ventas.</p>
                
                <div style="background-color: #ffcdd2; border: 1px solid #f44336; border-radius: 10px; padding: 20px; margin: 20px 0;">
                    <h4 style="margin: 0 0 15px 0; color: #c62828;">🚨 Detalles del Error</h4>
                    <p style="margin: 5px 0;"><strong>Mensaje:</strong> {{ $errorMessage }}</p>
                    <p style="margin: 5px 0;"><strong>Fecha:</strong> {{ date('d/m/Y H:i:s') }}</p>
                </div>
                
                <h4 style="color: #c62828; margin: 20px 0 10px 0;">🔧 Qué puede hacer:</h4>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>Intentar generar el reporte nuevamente desde el sistema</li>
                    <li>Verificar que el rango de fechas seleccionado sea correcto</li>
                    <li>Reducir el rango de fechas si el reporte es muy grande</li>
                    <li>Contactar al administrador del sistema si el problema persiste</li>
                </ul>
                
                <div style="background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 10px; padding: 15px; margin: 20px 0;">
                    <p style="margin: 0; color: #856404;"><strong>📝 Nota:</strong> Este error ha sido registrado en el sistema para su revisión.</p>
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
                    <br><small>Para soporte técnico, contacte al administrador del sistema.</small>
                </p>
            </td>
        </tr>
    </table>

</div>

</body>
</html>

