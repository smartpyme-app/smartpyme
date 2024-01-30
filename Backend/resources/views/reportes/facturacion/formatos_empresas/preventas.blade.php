<html>
<head>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/5.0.2/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <title>{{$empresa->nombre}} - Preventas</title>

<style>
    html{
        margin: 20px;
    }
    body {
        font-family: 'Inter', sans-serif;
        font-family: 'Nunito', sans-serif;
        width: 100%;
    }
    th, td{
        font-size: 14px;
        text-align: left;
        padding: 4px;
        border-bottom: solid, 0.5px;
        border-right: solid, 0.5px;
        border-color: lightgray;
        margin-left: 10px;
    }
    #empresa{
        margin-bottom: 0px;
        padding-top: 0px;
        text-align: center;
        margin-top: 0px;
        margin-bottom: 5px;
    }
    #cliente{
        display: inline;
    }
    p{
        font-size: 14px;
    }
    #producto{
        width: 440px;
    }
    #cantidad{
        width: 50px;
        border-left: solid, 0.5px;
        border-color: lightgray;
    }
    #totales{
        margin-top: 10px;
    }
    #table{
        margin-top: 0px;
        padding: 0px;
        width: 100%;
    }
    #headtable{
        padding: 10px;
        background-color: lightskyblue;
        margin: 0px;
    }
</style>
</head>
<body>
    <div class="row">
        <div class="col-lg-12 text-center">
            <h2 class="text-center" id="empresa">{{$empresa->nombre}}</h2>
            <p class="text-center" id="empresa">{{$empresa->direccion}}</p>
        </div>
        @foreach($ventas as $venta)
        <div class="col-lg-12 text-center">
            <h3><strong>Venta #: </strong>{{$loop->index + 1}}</h3>
        </div>
        
        <div class="col-lg-12" id="cliente">
            <p><strong>Cliente: </strong>{{$venta->cliente}}</p>
            <p><strong>Fecha: </strong>{{$venta->fecha}}</p>
        </div>
        <h4 class="" id="empresa">Detalles de la preventa</h4>
        <div class="col-lg-12">
        <table cellspacing="0" cellpadding="0" id="table">
          <thead id="headtable">
            <tr class="text-right">
              <th>Cantidad</th>
              <th id="producto">Producto</th>
              <th>Código</th>
              <th>Precio</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            @foreach($venta->detalles as $detalle)
            <tr class="text-right">
              <td id="cantidad">{{$detalle->cantidad}}</td>
              <td id="producto">{{$detalle->producto}}</td>
              <td>{{$detalle->codigo}}</td>
              <td>${{number_format($detalle->precio,2)}}</td>
              <td>${{number_format($detalle->total,2)}}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
        </div>
        <div class="col-lg-12 mt-3" id="totales">
            <p><strong>Sub total: </strong>${{number_format($venta->sub_total,2)}}</p>
            <p><strong>Impuestos: </strong>${{number_format($venta->iva,2)}}</p>
            <p><strong>Total: </strong>${{number_format($venta->total,2)}}</p>

        </div>
    </div>
    <hr>
    @endforeach
</body>

</html>
