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
            <img src="{{ asset('img/logo.png') }}" width="150" alt="Logo Wgas">
        </header>
        <div class="margin" style="width: 75%; margin: 30px auto; border: .1px solid #eee;"></div>
        <article style="width: 95%; text-align: justify; margin: auto;">

            <p>Estimado(a): <br>
                {{ $nombre }}
            </p>

            <p>Adjuntamos el Documento Tributario Electrónico con el siguiente detalle:</p>

            <p><b>Tipo de documento:</b> <br>
                @if($DTE['identificacion']['tipoDte'] == '01')
                    Factura
                @endif
                @if($DTE['identificacion']['tipoDte'] == '03')
                    Crédito Fiscal
                @endif
                @if($DTE['identificacion']['tipoDte'] == '14')
                    Factura Sujeto Excluido
                @endif
            </p>
            <p><b>Código de Generación:</b><br> {{ $DTE['identificacion']['codigoGeneracion'] }}</p>
            <p><b>Número de Control:</b><br> {{ $DTE['identificacion']['numeroControl'] }}</p>
            <p><b>Sello de Recepción:</b><br> {{ $DTE['sello'] }}</p>
            <p><b>Fecha y Hora de Generación:</b><br> {{ \Carbon\Carbon::parse($DTE['identificacion']['fecEmi'] . ' ' . $DTE['identificacion']['horEmi'])->format('d/m/Y H:i:s') }}</p>

            <br><br>
            <p>Este correo electrónico ha sido enviado automáticamente por favor no responder a esta cuenta, de requerir cualquier aclaratoria o información adicional debe comunicarse directamente a la dirección de correo electrónico o teléfono de nuestra empresa.</p>
            
        </article>
        <div class="margin" style="width: 75%; margin: 30px auto; border: .1px solid #eee;"></div>
        <footer>
            <p style="margin: 5px;">{{ $DTE['emisor']['nombre'] }} &copy; {{ date('y') }}</p>
            <p><b>Teléfono: </b>{{ $DTE['emisor']['telefono'] }}</p>
            <p><b>Correo: </b>{{ $DTE['emisor']['correo'] }}</p>
        </footer>
    </section>

</body>
</html>
