<html>
<head>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/5.0.2/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <title>{{$recibo->concepto}}</title>

<style>
    html{
        margin: 40px 60px;
    }
    body {
        font-family: 'Inter', sans-serif;
        font-family: 'Nunito', sans-serif;
        width: 100%;
    }
    th, td{
        font-size: 12px;
        text-align: left;
        padding: 6px;
        border: solid, 0.5px;
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
        margin: 5px;
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
    #footer{
      position: absolute;
      bottom: 0;
      width: 100%;
      text-decoration: none;
      font-size: 14px;
    }
    #footer a{
      text-decoration: none;
      color: #1775e5;
    }
    #sp{
        position: absolute;
        left: 24%;
        bottom: 1.5%;
    }
    .text-right{
        text-align: right !important;
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
            <p><b>Ticket #: </b>{{$recibo->id }}</p>
            <p><b>Empresa: </b>{{$recibo->empresa()->first()->nombre }}</p>
            <p><b>A nombre de: </b>{{$recibo->nombre_de }}</p>
            <p><b>Estado: </b><span style="background-color: #1775e5; color: #fff; padding: 7px;">{{$recibo->estado}}</span></p>
            <p><b>Fecha: </b>{{$recibo->created_at->format('d/m/Y h:m:s a')}}</p>
        </div><br>
        <div class="col-lg-12">
        <table cellspacing="0" cellpadding="0" id="table">
          <thead id="headtable">
            <tr class="text-right">
              <th>CANTIDAD</th>
              <th id="producto">CONCEPTO</th>
              <th class="text-right">PRECIO</th>
              <th class="text-right">TOTAL</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td id="cantidad"><center>1</center></td>
              <td id="producto">{{$recibo->concepto}}</td>
              <td class="text-right">${{number_format(($recibo->monto / 1.13),2)}}</td>
              <td class="text-right">${{number_format(($recibo->monto / 1.13),2)}}</td>
            </tr>
          </tbody>
            <tfoot>
            <tr>
                <td class="text-right" colspan="3"><b>Sub total: </b></td>
                <td class="text-right">${{number_format(($recibo->monto / 1.13),2)}}</td>
            </tr>
            <tr>
                <td class="text-right" colspan="3"><b>Impuesto: </b></td>
                <td class="text-right">${{number_format((($recibo->monto / 1.13) * 0.13),2)}}</td>
            </tr>
            <tr>
                <td class="text-right" colspan="3"><b>Total a pagar: </b></td>
                <td class="text-right"><b>${{number_format($recibo->monto,2)}}</b></td>
            </tr>
            </tfoot>
        </table>
        </div>

        <br>
        <br>
        <br>

        <div class="col-lg-12 mt-3" id="totales">
            <p>
                Para realizar el pago por favor contactar:
                <br>
                Tel.: +503 7723-5932
                <br>
                Email: gabrielaq@analyticsas.com
            </p>
        </div>
    </div>
    <center>
        <div id="footer">
            <p>SmartPyme Technologies S.A DE S.V</p>
            <a href="https://www.smartpyme.sv/">smartpyme.sv</a>
        </div>
    </center>
</body>
</html>
