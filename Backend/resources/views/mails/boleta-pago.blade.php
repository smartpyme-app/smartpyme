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
            <h2>{{ $empresa->nombre }}</h2>
            <p>Boleta de Pago - {{ $planilla->codigo }}</p>
        </header>
        <div class="margin" style="width: 75%; margin: 30px auto; border: .1px solid #eee;"></div>
        <article style="width: 95%; text-align: justify; margin: auto;">

            <p>Estimado(a): <br>
                {{ $empleado->nombres }} {{ $empleado->apellidos }}
            </p>

            <p>Adjuntamos su Boleta de Pago con el siguiente detalle:</p>

            <p><b>Período:</b><br>
                {{ date('d/m/Y', strtotime($planilla->fecha_inicio)) }} - {{ date('d/m/Y', strtotime($planilla->fecha_fin)) }}
            </p>
            <p><b>Código de Planilla:</b><br> {{ $planilla->codigo }}</p>
            <p><b>Fecha de Emisión:</b><br> {{ date('d/m/Y H:i:s') }}</p>

            <br><br>
            <p>Por favor, revise los detalles en el documento PDF adjunto. Si tiene alguna pregunta o nota alguna discrepancia, debe comunicarse directamente al departamento de Recursos Humanos.</p>
            
            <p>Este correo electrónico ha sido enviado automáticamente por favor no responder a esta cuenta, de requerir cualquier aclaratoria o información adicional debe comunicarse directamente a la dirección de correo electrónico o teléfono de nuestra empresa.</p>
            
        </article>
        <div class="margin" style="width: 75%; margin: 30px auto; border: .1px solid #eee;"></div>
        <footer>
            <p style="margin: 5px;">{{ $empresa->nombre }} &copy; {{ date('Y') }}</p>
            @if($empresa->telefono)
                <p><b>Teléfono: </b>{{ $empresa->telefono }}</p>
            @endif
            @if($empresa->email)
                <p><b>Correo: </b>{{ $empresa->email }}</p>
            @endif
        </footer>
    </section>

</body>
</html>