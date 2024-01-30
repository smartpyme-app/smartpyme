<html>
<head>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/5.0.2/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <title>{{$venta->cliente}}</title>

<style>
    html{
        margin: 40px 60px;
    }
    body {
        font-family: 'Inter', sans-serif;
        font-family: 'Nunito', sans-serif;
        width: 100%;
    }

    #head{
        border-collapse: collapse;
        width: 100%;
    }
    #head th, #head td{
        border: none;
    }

    #table{
        border-collapse:collapse;
        width: 100%;
        text-align: left;
    }

    #table th, #table td{
        border: 1px solid #555;
        padding: 5px;
    }

    .text-left{
        text-align: left;
    }
    .text-right{
        text-align: right;
    }
    .text-center{
        text-align: center;
    }
</style>
</head>
<body>
        
        <table id="head">
            <td><h1>{{Auth::user()->empresa}}</h1></td>
            <td class="text-right"><p>{{Carbon\Carbon::now()->format('d/m/Y h:i:s a')}}</p></td>
        </table>

        <h2 class="text-center">Comprobante de pago</h2>

        <table id="head">
            <td><p><b>Cliente: </b><br>{{$venta->cliente}}</p></td>
            <td><p><b>Forma pago: </b><br>{{$recibo->forma_pago}}</p></td>
            <td><p><b>Fecha pago: </b><br>{{\Carbon\Carbon::parse($recibo->fecha)->format('d/m/Y')}}</p></td>
        </table>

        <table id="table">
            <thead>
                <tr>
                  <th class="text-left">Fecha</th>
                  <th class="text-left">Descripción</th>
                  <th class="text-left">Factura</th>
                  <th class="text-right">Pago</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                  <td>{{\Carbon\Carbon::parse($recibo->fecha)->format('d/m/Y')}}</td>
                  <td>{{$recibo->concepto}}</td>
                  <td>{{$recibo->documento}} {{$venta->correlativo}}</td>
                  <td class="text-right">${{number_format($recibo->total,2)}}</td>
                </tr>
            </tbody>
        </table>

        <br>
        <br>
        <br>

        <table>
            <tr>
                <td>
                    <b>Abonos: </b>
                </td>
                <td>
                    ${{number_format($recibo->total,2)}}
                </td>
            </tr>
            <tr>
                <td>
                    <b>Saldo: </b>
                </td>
                <td>
                    <b>${{number_format($venta->saldo,2)}}</b>
                </td>
            </tr>
        </table>

</body>

</html>
