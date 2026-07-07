<div style="padding: 15px; font-family: sans-serif;">
    

    <table style="width: 100%;">
        <tr>
            <td style="text-align: center;">
                <img width="150px" src="https://www.smartpyme.sv/wp-content/uploads/2022/09/logo-web-smartpyme-2022-new.png" alt="Logo SmartPyme">
                <div style="margin-top: 15px; border-bottom: 1px solid #cecece;">
            </td>
        </tr>
        <tr>
            <td style="padding: 50px 25px 0px 25px;">
                
                <h2 style="color: #333;">Recupera tu cuenta</h2>

                <p>Hola,</p>

                <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta. Haz clic en el siguiente botón para crear una nueva contraseña:</p>

                <p style="text-align: center; margin: 30px 0;">
                    <a href="{{ $resetUrl }}" style="background-color: #1775e5; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">
                        Restablecer contraseña
                    </a>
                </p>

                <p>Este enlace expirará en {{ config('auth.passwords.'.config('auth.defaults.passwords').'.expire') }} minutos.</p>

                <p>Si no solicitaste un cambio de contraseña, puedes ignorar este correo. Tu contraseña no será modificada.</p>
                
            </td>
        <tr>
    </table>
    <table style="width: 100%;">
        

         <tr>
            <td>
                <p style="margin: 15px 0px; text-align: center; color: gray;">
                    <br><br>
                    Saludos,<br>
                    <a href="https://app.smartpyme.site" target="blank">Smartpyme</a><br>
                    <p>San Salvador, El Salvador</p>
                </p>
            </td>
        </tr>
    </table>

</div>
