<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Entrada</title>
</head>


<style>
    @page { 
        margin: 2cm 2.5cm;
    }
    .text-left { text-align: left; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    *{ font-family: sans-serif; color: #333; }
    .header { position: fixed; top: -50px; width: 100%;}
    .header h1 { position: absolute; top: -15px; left: 100px; font-size: 16px;}
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
    @if ($empresa->logo)
            <img height="70" src="{{ asset('img/'.$empresa->logo) }}" alt="Logo">
        @endif
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
                <h2 class="text-center m-0 p-0">Entrada # {{ $entrada->id }}</h2>
            </th>
        </tr>
        <tr>
            <td>
                <b>Realizada por:</b> {{ $entrada->usuario_nombre }}
            </td>
            <td>
                <b>Fecha:</b> {{ \Carbon\Carbon::parse($entrada->fecha)->format('d/m/Y') }} {{ \Carbon\Carbon::parse($entrada->created_at)->format('h:i:s a') }}
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <b>Bodega:</b> {{ $entrada->bodega_nombre }} 
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <b>Concepto:</b> {{ $entrada->concepto }}</p>
            </td>
        </tr>
    </table>

    <br>
    <table>
        <thead>
            <tr>
                <th class="text-center">N°</th>
                <th class="text-left">Producto</th>
                <th class="text-center">Cantidad</th>
                <th class="text-center">Costo</th>
                <th class="text-center">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($entrada->detalles as $key => $detalle)
            <tr>
                <td class="text-center">{{ $key + 1}}</td>
                <td>{{ $detalle->nombre_producto }}</td>
                <td class="text-center">{{ number_format($detalle->cantidad,0) }}</td>
                <td class="text-center">${{ number_format($detalle->costo,2) }}</td>
                <td class="text-center">${{ number_format($detalle->total,2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td class="text-right" colspan="2"><b>Total:</b></td>
                <td class="text-center"><b>{{ number_format($entrada->detalles->sum('cantidad'), 0) }}</b></td>
                <td class="text-center"><b>${{ number_format($entrada->detalles->sum('costo'),2) }}</b></td>
                <td class="text-center"><b>${{ number_format($entrada->detalles->sum('total'),2) }}</b></td>
            </tr>
        </tfoot>
    </table>

</body>
</html>
