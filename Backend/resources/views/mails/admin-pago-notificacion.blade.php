<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="UTF-8">
    <title>SmartPyme - Notificación de Pago</title>
</head>

<body style="font-family: arial, helvetica; color: #555; background-color: #eee;" bgcolor="#eee">
    
    <section style="width: 95%; text-align: center; border-radius: 15px; background-color: #fff; margin: auto; padding: 10px;">
        <header>
            <!-- Logo de SmartPyme -->
            <div style="text-align: center; padding: 20px 0;">
                <img width="150px" src="https://www.smartpyme.sv/wp-content/uploads/2022/09/logo-web-smartpyme-2022-new.png" alt="Logo SmartPyme">
                <div style="margin-top: 15px; border-bottom: 1px solid #cecece;"></div>
            </div>
            
            <h2>Notificación de Pago</h2>
        </header>
        <div class="margin" style="width: 75%; margin: 30px auto; border: .1px solid #eee;"></div>
        <article style="width: 95%; text-align: justify; margin: auto;">

            <p>Se ha registrado un pago en el sistema:</p>

            <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                <tr style="background-color: #f8f8f8;">
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Empresa</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $empresa->nombre }}</td>
                </tr>
                <tr>
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Cliente</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $usuario->name }}</td>
                </tr>
                <tr style="background-color: #f8f8f8;">
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Email</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $usuario->email }}</td>
                </tr>
                <tr>
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Teléfono</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $usuario->telefono ?: 'No disponible' }}</td>
                </tr>
                <tr style="background-color: #f8f8f8;">
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Plan</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $suscripcion->plan->nombre }}</td>
                </tr>
                <tr>
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Tipo de Plan</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $suscripcion->tipo_plan }}</td>
                </tr>
                <tr style="background-color: #f8f8f8;">
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Monto Pagado</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">${{ number_format($ordenPago->monto, 2) }}</td>
                </tr>
                <tr>
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Fecha de Pago</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ date('d/m/Y H:i:s', strtotime($ordenPago->fecha_transaccion)) }}</td>
                </tr>
                <tr style="background-color: #f8f8f8;">
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Orden ID</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $ordenPago->id_orden }}</td>
                </tr>
                <tr>
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Autorización</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $ordenPago->codigo_autorizacion ?: 'N/A' }}</td>
                </tr>
                <tr style="background-color: #f8f8f8;">
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Estado</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $ordenPago->estado }}</td>
                </tr>
                <tr>
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Tipo de Suscripción</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $esNuevaSuscripcion ? 'Nueva Suscripción' : 'Renovación' }}</td>
                </tr>
            </table>
            
        </article>
        <div class="margin" style="width: 75%; margin: 30px auto; border: .1px solid #eee;"></div>
        <footer>
            <p style="margin: 5px;">SmartPyme &copy; {{ date('Y') }}</p>
            <p><a href="https://smartpyme.sv" target="_blank" style="color: #1775e5; text-decoration: none;">smartpyme.sv</a></p>
        </footer>
    </section>

</body>
</html>