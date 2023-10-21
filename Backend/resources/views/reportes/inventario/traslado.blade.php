<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Traslado</title>
</head>


<style>
    @page { 
        margin: 2cm 2.5cm;
    }
    .text-left { text-align: left; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    *{ font-family: sans-serif; color: #333; }
    .header { position: fixed; top: -50px;}
    .header h1 { position: absolute; top: -15px; left: 100px; font-size: 25px;}
    .header p { position: absolute; top: 15px; left: 100px;}
    .footer{ position: fixed; bottom: -10px; opacity: .6; }
    .footer span {position: absolute; bottom: 0px; right: 0px; counter-increment: page;}
    .content{ margin-top: 40px; }
    .bg{ width: 840px; position: fixed; top: -150px; left: -120px; opacity: .5; z-index: -1;}
    table {width: 100%; border-collapse: collapse; margin: auto; page-break-inside: auto; }
    tr{ page-break-inside:avoid; page-break-after:auto }
    td, th, td, {border: 0.5px solid gray; padding: 5px 10px; }
    hr{ border: 0.5px solid #D1D0D0; }
    .notas>br:before {content: "*"; color: black; }
    p{ text-align: justify; }
    .badge{ background-color: #1B5FFA; color: white; padding: 5px;}
    .completado{text-decoration:line-through; color: gray;}
    .text-uppercase{text-transform: uppercase;}
    .m-0{ margin:0px; }
</style>

<body>

    <div class="header">
        <img src="img/logo.jpg" alt="logo" width="70">
        <h1 class="text-center">{{ $empresa->nombre }}</h1>
        <p class="text-center">{{ $empresa->sector }}</p>
    </div>
    <div class="footer">
        <hr>
        <h4 class="text-center">
            {{ $empresa->nombre }} | {{ $empresa->correo }} | {{ $empresa->telefono }}
        </h4>
    </div>

    <table class="content">
        <tr>
            <th colspan="2">
                <h2 class="text-center m-0 p-0">Orden de Traslado # {{ $traslado->id }}</h2>
            </th>
        </tr>
        <tr>
            <td>
                <b>Realizada por:</b> {{ $traslado->usuario }}
            </td>
            <td>
                <b>Fecha:</b> {{ \Carbon\Carbon::parse($traslado->fecha)->format('d/m/Y') }} 
                {{-- <b>Hora:</b> {{ \Carbon\Carbon::parse($traslado->fecha)->format('h:m:s A') }} --}}
            </td>
        </tr>
        <tr>
            <td>
                <b>De:</b> {{ $traslado->origen->nombre }} 
            </td>
            <td>
                <b>Para:</b> {{ $traslado->destino->nombre }}
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <b>Nota:</b> {{ $traslado->nota }}</p>
            </td>
        </tr>
    </table>

    <br>
    <table>
        <thead>
            <tr>
                <th class="text-center">N°</th>
                <th class="text-left">Producto</th>
                <th class="text-center">Medida</th>
                <th class="text-center">Cantidad</th>
            </tr>
        </thead>
        <tbody>
            @foreach($traslado->detalles as $key => $detalle)
            <tr>
                <td class="text-center">{{ $key + 1}}</td>
                <td>{{ $detalle->nombre_producto }}</td>
                <td class="text-center">{{ $detalle->medida }}</td>
                <td class="text-center">{{ $detalle->cantidad }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td class="text-right" colspan="3"><b>Total:</b></td>
                <td class="text-center"><b>{{ number_format($traslado->detalles->sum('cantidad')) }}</b></td>
            </tr>
        </tfoot>
    </table>

</body>
</html>