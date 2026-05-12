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
            <div style="text-align: center; padding: 20px 0;">
                <img width="150px" src="https://www.smartpyme.sv/wp-content/uploads/2022/09/logo-web-smartpyme-2022-new.png" alt="Logo SmartPyme">
                <div style="margin-top: 15px; border-bottom: 1px solid #cecece;"></div>
            </div>
            <h2>{{ $empresa->nombre }}</h2>
            <p>SmartPyme — Suscripción</p>
        </header>
        <div class="margin" style="width: 75%; margin: 30px auto; border: .1px solid #eee;"></div>
        <article style="width: 95%; text-align: justify; margin: auto;">
            <p>Estimado(a) {{ $usuario->name }},</p>

            @if($tipo === 'recordatorio_previo')
                <p>Recuerda que tu fecha de pago programada es el <strong>{{ $fecha_proximo_pago_texto }}</strong>. Te invitamos a mantener al día tus <strong>saldos pendientes con el sistema</strong> para evitar interrupciones en el servicio.</p>
            @elseif($tipo === 'alerta_vencimiento')
                <p>Hoy vence el plazo programado de tu suscripción respecto a la fecha <strong>{{ $fecha_proximo_pago_texto }}</strong>. Puedes <strong>regularizar tus saldos con el sistema</strong> para continuar sin interrupciones.</p>
            @elseif($tipo === 'advertencia_urgente')
                <p><strong>Importante:</strong> si persisten <strong>saldos pendientes con el sistema</strong>, mañana podría <strong>limitarse el acceso</strong> a la plataforma. Te invitamos a regularizar tu situación a la brevedad.</p>
            @else
                <p>Tu suscripción requiere atención. Ingresa a SmartPyme para más detalles.</p>
            @endif

            <p style="margin-top: 24px;">Si ya regularizaste tu situación, puedes ignorar este mensaje.</p>
            <p>Para cualquier consulta, estamos a tu disposición.</p>
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
