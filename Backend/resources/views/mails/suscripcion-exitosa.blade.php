<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="UTF-8">
    <title>SmartPyme</title>
</head>

<body style="font-family: arial, helvetica; color: #555; background-color: #eee;" bgcolor="#eee">
    
    <section style="width: 95%; text-align: center; border-radius: 15px; background-color: #fff; margin: auto; padding: 10px;">
        <header>
            <!-- Logo de SmartPyme -->
            <div style="text-align: center; padding: 20px 0;">
                <img width="150px" src="https://www.smartpyme.sv/wp-content/uploads/2022/09/logo-web-smartpyme-2022-new.png" alt="Logo SmartPyme">
                <div style="margin-top: 15px; border-bottom: 1px solid #cecece;"></div>
            </div>
            
            <h2>{{ $empresa->nombre }}</h2>
            <p>Confirmación de Suscripción</p>
        </header>
        <div class="margin" style="width: 75%; margin: 30px auto; border: .1px solid #eee;"></div>
        <article style="width: 95%; text-align: justify; margin: auto;">

            <p>Estimado(a):<br>
                {{ $usuario->name }}
            </p>

            <p>¡Gracias por suscribirse a SmartPyme! Su pago ha sido procesado exitosamente y su suscripción está ahora activa.</p>

            <p><b>Detalles de la suscripción:</b></p>
            
            <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                <tr style="background-color: #f8f8f8;">
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Plan</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $suscripcion->plan->nombre }}</td>
                </tr>
                <tr>
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Tipo de Plan</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $suscripcion->tipo_plan }}</td>
                </tr>
                <tr style="background-color: #f8f8f8;">
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Monto</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">${{ number_format($suscripcion->monto, 2) }}</td>
                </tr>
                <tr>
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Fecha de inicio</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ date('d/m/Y', strtotime($suscripcion->fecha_ultimo_pago)) }}</td>
                </tr>
                <tr style="background-color: #f8f8f8;">
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Próxima facturación</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ date('d/m/Y', strtotime($suscripcion->fecha_proximo_pago)) }}</td>
                </tr>
                @if($empresa->pago_recurrente)
                <tr>
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Renovación automática</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">Activada</td>
                </tr>
                @endif
            </table>

            <p>Ahora puede disfrutar de todas las funcionalidades disponibles en su plan. Si tiene alguna pregunta o necesita ayuda, no dude en contactarnos.</p>
            
            <p>¡Le damos la bienvenida a la familia SmartPyme!</p>
            
        </article>
        <div class="margin" style="width: 75%; margin: 30px auto; border: .1px solid #eee;"></div>
        <footer>
            <p style="margin: 5px;">SmartPyme &copy; {{ date('Y') }}</p>
            <p><b>Teléfono: </b>+503 7767-5850</p>
            <p><b>Correo: </b>contact@smartpyme.sv</p>
            <p><a href="https://smartpyme.sv" target="_blank" style="color: #1775e5; text-decoration: none;">smartpyme.sv</a></p>
        </footer>
    </section>

</body>
</html>