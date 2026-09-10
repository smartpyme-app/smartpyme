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
            <p>SmartPyme — Préstamos</p>
        </header>
        <div style="width: 75%; margin: 30px auto; border: .1px solid #eee;"></div>
        <article style="width: 95%; text-align: justify; margin: auto;">
            <p>Estimado(a) equipo de {{ $empresa->nombre }},</p>

            <p>
                @if($dias === 0)
                    Hoy vence(n) la(s) siguiente(s) cuota(s) de préstamo:
                @elseif($dias === 1)
                    Mañana ({{ $fecha_vencimiento_texto }}) vence(n) la(s) siguiente(s) cuota(s) de préstamo:
                @else
                    En {{ $dias }} días ({{ $fecha_vencimiento_texto }}) vence(n) la(s) siguiente(s) cuota(s) de préstamo:
                @endif
            </p>

            <table style="width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px;">
                <thead>
                    <tr style="background-color: #f5f5f5;">
                        <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Préstamo</th>
                        <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">Cuota</th>
                        <th style="padding: 8px; border: 1px solid #ddd; text-align: right;">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cuotas as $cuota)
                        <tr>
                            <td style="padding: 8px; border: 1px solid #ddd;">{{ $cuota->prestamo->acreedor }}</td>
                            <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">#{{ $cuota->numero }}</td>
                            <td style="padding: 8px; border: 1px solid #ddd; text-align: right;">${{ number_format((float) $cuota->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p>Te recomendamos registrar el pago a tiempo en el módulo de Finanzas → Préstamos.</p>

            @if(!empty($app_url))
                <p style="text-align: center; margin: 24px 0;">
                    <a href="{{ $app_url }}/finanzas/prestamos" target="_blank" style="background-color: #1775e5; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;">
                        Ver préstamos en SmartPyme
                    </a>
                </p>
            @endif

            <p style="margin-top: 24px;">Si ya registraste el pago, puedes ignorar este mensaje.</p>
        </article>
        <div style="width: 75%; margin: 30px auto; border: .1px solid #eee;"></div>
        <footer>
            <p style="margin: 5px;">SmartPyme &copy; {{ date('Y') }}</p>
            <p><b>Teléfono: </b>+503 7767-5850</p>
            <p><b>Correo: </b>contact@smartpyme.sv</p>
            <p><a href="https://smartpyme.sv" target="_blank" style="color: #1775e5; text-decoration: none;">smartpyme.sv</a></p>
        </footer>
    </section>
</body>
</html>
