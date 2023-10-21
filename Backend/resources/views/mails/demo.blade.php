<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="UTF-8">
    <title>Demo - Wanda</title>
</head>

<body style="font-family: arial, helvetica; color: #555; background-color: #eee;" bgcolor="#eee">
    
    <section style="width: 95%; text-align: center; border-radius: 15px; background-color: #fff; margin: auto; padding: 10px;">
        <header>
            <img src="{{ asset('img/logo.png') }}" width="150" alt="Logo Wgas">
        </header>
        <div class="margin" style="width: 75%; margin: 30px auto; border: .1px solid #eee;"></div>
        <article style="width: 95%; text-align: justify; margin: auto;">
            
            <p style="margin: 5px;"><b>Escribio</b> : {{ $request->nombre }}</p>
            
            <p style="margin: 5px;"><b>Su correo es</b> : {{ $request->correo }}</p>

            <p style="margin: 5px;"><b>Su teléfono es</b> : {{ $request->telefono }}</p>
            
        </article>
        <div class="margin" style="width: 75%; margin: 30px auto; border: .1px solid #eee;"></div>
        <footer>
            <p style="margin: 5px;">Wanda &copy; {{ date('y') }}</p>
        </footer>
    </section>

</body>
</html>
