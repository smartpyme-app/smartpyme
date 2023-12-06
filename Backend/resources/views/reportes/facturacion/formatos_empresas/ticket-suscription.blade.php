<html>
<head>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/5.0.2/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <title>{{$transaccion->descripcion}}</title>

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
        font-size: 12px;
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
        width: 240px;
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
        background-color: #1775e5;
        margin: 0px;
        color: white;
    }
    #img{
        height: 25px;
        margin-bottom: 15px;
    }
    #footer a{
      position: absolute;
      bottom: 0;
      left: 40%;
      text-decoration: none;
      font-size: 14px;
      color: #1775e5;
    }
    #sp{
        position: absolute;
        left: 24%;
        bottom: 1.5%;
    }
</style>
</head>
<body>
    <div class="row">
        <div class="col-lg-12 text-center">
            <center>
            <img src="{{ public_path('img/SmartPyme-logo-blue.png') }}" id="img"></center>
            <p class="text-center" id="empresa">San Salvador, El Salvador</p>
        </div><br><br>
        <h4 class="" id="empresa">Detalles de la compra</h4><br>
        <div class="col-lg-12" id="cliente">
            <p><strong>Concepto: </strong>{{$transaccion->descripcion}}</p>
            <p><strong>Fecha: </strong>{{$transaccion->created_at}}</p>
        </div><br>
        <div class="col-lg-12">
        <table cellspacing="0" cellpadding="0" id="table">
          <thead id="headtable">
            <tr class="text-right">
              <th>Cantidad</th>
              <th id="producto">Concepto</th>
              <th>Precio</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            <tr class="text-right">
              <td id="cantidad"><center>1</center></td>
              <td id="producto">{{$transaccion->descripcion}}</td>
              <td>${{number_format(($transaccion->monto / 1.13),2)}}</td>
              <td>${{number_format(($transaccion->monto / 1.13),2)}}</td>
            </tr>
          </tbody>
        </table>
        </div><br>
        <div class="col-lg-12 mt-3" id="totales">
            <p><strong>Sub total: </strong>${{number_format(($transaccion->monto / 1.13),2)}}</p>
            <p><strong>Impuesto: </strong>${{number_format((($transaccion->monto / 1.13) * 0.13),2)}}</p>
            <p><strong>Total a pagar: </strong>${{number_format($transaccion->monto,2)}}</p>
        </div>
        <div class="col-lg-12 mt-3" id="totales">
            <p>Si desea solicitar crédito fiscal por favor contactar al tel. +503 7723-5932 Email: gabrielaq@analyticsas.com</p>
        </div>
    </div>
    <center>
        <div id="sp">
            <p>SmartPyme Technologies S.A DE S.V</p>
        </div>
    <div id="footer">
        <a href="https://www.smartpyme.sv/">smartpyme.sv</a>
    </div>
    </center>
</body>
</html>