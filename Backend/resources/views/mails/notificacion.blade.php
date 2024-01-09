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
            <div style="border-bottom: 1px solid #cecece;">
        </td>
    </tr>
    <tr>
        <td style="text-align: center; padding-top: 10px; padding-bottom: 15px;">
            <h3>Notificación</h3>
        </td>
    </tr>
    <tr>
        <td style="padding: 50px 25px; background-color: #9e9e9e47; border-radius: 30px; margin: 15px 0px;">
            <h3 style="margin: 0px;">{{ $data['titulo'] }}</h3>

            <p style="margin: 15px 0px;">{{ $data['descripcion'] }}</p>
        </td>
    </tr>
</table>
<table style="width: 100%;">
    
    <tr>
        <td>
            <div style="border-bottom: 1px solid #cecece;">
        </td>
    </tr>

     <tr>
        <td>
            <p style="margin: 15px 0px; text-align: center; color: gray;">
                Smartpyme | © {{ date('Y')}}
            </p>
        </td>
    </tr>
</table>

</div>

</body>
</html>
