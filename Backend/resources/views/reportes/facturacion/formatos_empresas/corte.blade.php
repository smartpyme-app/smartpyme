<html>
<head>
    <title>Corte</title>

<style>
    @page{
        margin: 20px 100px;
    }
    body {
        font-family: 'Inter', sans-serif;
        font-family: 'Nunito', sans-serif;
        width: 100%;
    }
    th, td{
        font-size: 12px;
        text-align: left;
        padding: 5px;
        border: solid, 0.5px;
        border-color: lightgray;
        margin-left: 10px;
    }
    h3, h5 {
        font-size: 1.1rem;
        margin-bottom: 10px;
    }
    p{
        font-size: 0.6rem;
        margin: 5px;
    }
    table {
        border-collapse: collapse;
    }
    .table-bordered, .table-bordered td, .table-bordered th {
        border: 1px solid #dee2e6;
    }

    .table td, .table th {
        padding: 0.75rem;
        vertical-align: top;
        border-top: 1px solid #dee2e6;
    }
    .table-bordered, .table-bordered td, .table-bordered th {
        border: 1px solid #dee2e6;
    }

    .table {
        width: 100%;
        margin-bottom: 1rem;
        background-color: transparent;
    }
    .headtable{
        padding: 10px;
        background-color: #1775e5;
        margin: 0px;
        color: white;
    }
    #img{
        height: 150px;
        margin-bottom: 0px;
    }
    #footer{
      position: absolute;
      bottom: 0;
      width: 100%;
      text-decoration: none;
      font-size: 14px;
    }
    li{
        margin-left: 30px;
    }
    #footer a{
      text-decoration: none;
      color: #1775e5;
    }
    .text-white {
        color: #fff!important;
    }

    .bg-primary {
        background-color: #3490dc!important;
    }
    .text-right{
        text-align: right !important;
    }
    .text-center{
        text-align: center !important;
    }
    .text-success {
        color: #38c172!important;
    }
    .text-info {
        color: #6cb2eb!important;
    }
    .text-secondary {
        color: #6c757d!important;
    }
    .text-danger {
        color: #e3342f!important;
    }
    caption{
        margin-bottom: 5px;
    }
</style>
</head>
<body>
    <div class="row">
        <div class="col-lg-12 text-center">
            <center>
           {{--  @if ($indicadores->empresa->logo)
                <img src="{{ asset($indicadores->empresa->logo) }}" id="img"></center>
            @endif --}}
            <p class="text-center" id="empresa">{{ $indicadores->empresa->nombre }}</p>
        </div>
        <h2 style="margin:0px;" class="text-center">Corte del día</h2>
        <p style="margin-bottom:10px; font-size: 14px;" class="text-center">
            @if ($indicadores->sucursal)
                <b>Sucursal: </b>{{ $indicadores->sucursal->nombre }}
            @else
                <b>Sucursal: </b>Todas
            @endif
            <b style="margin-left: 100px;">Fecha: </b>{{\Carbon\Carbon::parse($indicadores->fecha)->format('d/m/Y')}}
        </p>

        {{-- @include('administracion.corte.tablas') --}}
        @include('administracion.corte.resumen-de-caja')

    </div>
    <center>
        <div id="footer">
            <p>SmartPyme Technologies S.A DE S.V</p>
            <a href="https://www.smartpyme.sv/">smartpyme.sv</a>
        </div>
    </center>
</body>
</html>
