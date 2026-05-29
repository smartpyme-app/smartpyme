<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="UTF-8">
    <title>SmartPyme - Nueva empresa ÁBACO</title>
</head>

<body style="font-family: arial, helvetica; color: #555; background-color: #eee;" bgcolor="#eee">

    <section style="width: 95%; text-align: center; border-radius: 15px; background-color: #fff; margin: auto; padding: 10px;">
        <header>
            <!-- Logo de SmartPyme -->
            <div style="text-align: center; padding: 20px 0;">
                <img width="150px" src="https://www.smartpyme.sv/wp-content/uploads/2022/09/logo-web-smartpyme-2022-new.png" alt="Logo SmartPyme">
                <div style="margin-top: 15px; border-bottom: 1px solid #cecece;"></div>
            </div>

            <h2>Nueva empresa registrada — Alianza ÁBACO</h2>
        </header>

        <div style="width: 75%; margin: 30px auto; border: .1px solid #eee;"></div>

        <article style="width: 95%; text-align: justify; margin: auto;">

            <p>Se ha completado el registro de una nueva empresa a través del portal <strong>ÁBACO</strong>. A continuación los detalles:</p>

            <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                <tr style="background-color: #f8f8f8;">
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Empresa</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $empresa->nombre }}</td>
                </tr>
                <tr>
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Propietario</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $nombrePropietario }}</td>
                </tr>
                <tr style="background-color: #f8f8f8;">
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Correo</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $correo }}</td>
                </tr>
                <tr>
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Teléfono</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $telefono ?: 'No disponible' }}</td>
                </tr>
                <tr style="background-color: #f8f8f8;">
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Plan</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $plan }}</td>
                </tr>
                <tr>
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Frecuencia de pago</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $tipoPlan }}</td>
                </tr>
                <tr style="background-color: #f8f8f8;">
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Total a pagar</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $empresa->moneda ?? 'USD' }} {{ number_format($empresa->total, 2) }}</td>
                </tr>
                <tr>
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">País</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $empresa->pais ?? 'N/D' }}</td>
                </tr>
                <tr style="background-color: #f8f8f8;">
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Campaña</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $empresa->campania ?? 'ÁBACO' }}</td>
                </tr>
                <tr>
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Fecha de registro</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ now()->format('d/m/Y H:i:s') }}</td>
                </tr>
                <tr style="background-color: #f8f8f8;">
                    <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Origen del registro</th>
                    <td style="padding: 10px; border: 1px solid #ddd;">{{ $origenRegistro }}</td>
                </tr>
            </table>

            <p>Por favor realicen el seguimiento correspondiente para dar la bienvenida a esta empresa registrada a través de la alianza con ÁBACO.</p>

        </article>

        <div style="width: 75%; margin: 30px auto; border: .1px solid #eee;"></div>

        <footer>
            <p style="margin: 5px;">SmartPyme &copy; {{ date('Y') }}</p>
            <p><a href="https://smartpyme.sv" target="_blank" style="color: #1775e5; text-decoration: none;">smartpyme.sv</a></p>
        </footer>
    </section>

</body>
</html>
