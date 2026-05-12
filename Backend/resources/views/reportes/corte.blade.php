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
            @if ($indicadores->id_sucursal)
                <b>Sucursal: </b>{{ $indicadores->sucursal()->pluck('nombre')->first() }}
            @else
                <b>Sucursal: </b>Todas
            @endif
            <b style="margin-left: 100px;">Fecha: </b>{{\Carbon\Carbon::parse($indicadores->inicio)->format('d/m/Y')}}
        </p>
        @php $simbolo_moneda = optional($indicadores->empresa->currency)->currency_symbol ?? '$'; @endphp

        <table class="table table-bordered table-hover border-primary mb-3">
            <tr>
                <td width="16%">
                    <h3 class="text-center text-success">
                        {{ $simbolo_moneda }}{{ $indicadores->getTotalVentas() }}
                    </h3>
                    <p class="mb-0 text-center">TOTAL <br> VENTAS </p>
                </td>
                <td width="17%">
                    <h3 class="text-center text-info">
                        {{ $simbolo_moneda }}{{ $indicadores->getTotalRecibos() }}
                    </h3>
                    <p class="mb-0 text-center">TOTAL <br> ABONOS</p>
                </td>
                <td width="17%">
                    <h3 class="text-center text-secondary">
                        {{ $simbolo_moneda }}{{ $indicadores->getTotalVentasPendientes() }}
                    </h3>
                    <p class="mb-0 text-center">TOTAL <br> CREDITOS</p>
                </td>
                <td width="17%">
                    <h3 class="text-center text-danger">
                        {{ $simbolo_moneda }}{{ $indicadores->getTotalDevolucionesVenta() }}
                    </h3>
                    <p class="mb-0 text-center">TOTAL <br> DEVOLUCIONES</p>
                </td>
                <td width="16%">
                    <h3 class="text-center text-success">
                        {{ $simbolo_moneda }}{{ $indicadores->getTotalVentasSinDevoluciones() }}
                    </h3>
                    <p class="mb-0 text-center">VENTAS TOTALES <br> SIN DEVOLUCIONES</p>
                </td>
                <td width="17%">
                    <h3 class="text-center text-secondary">
                        {{ $simbolo_moneda }}{{ $indicadores->getTotalGastosPagados() }}
                    </h3>
                    <p class="mb-0 text-center">TOTAL <br> GASTOS</p>
                </td>
            </tr>
        </table>

        <h5 class="font-weight-bold text-uppercase my-3">
            <i class="fa-solid fa-square-pen"></i> Resumen de ventas
        </h5>

        <table class="table table-bordered table-hover border-primary mb-3">
            <thead>
                <tr class="bg-primary text-white">
                    <th>Detalle</th>
                    <th class="text-right" width="20%">N° de transacciones</th>
                    <th class="text-right" width="17%">Total</th>
                </tr>
            </thead>
          <tbody>
            <tr>
                <td>Ventas del día</td>
                <td class="text-right">{{ $indicadores->getCantidadVentasPagadas() }}</td>
                <td class="text-right">{{ $simbolo_moneda }}{{number_format($indicadores->getTotalVentasPagadas(), 2) }}</td>
            </tr>
            <tr>
                <td>Ventas al crédito</td>
                <td class="text-right">{{ $indicadores->getCantidadVentasPendientes() }}</td>
                <td class="text-right">{{ $simbolo_moneda }}{{ number_format($indicadores->getTotalVentasPendientes(), 2) }}</td>
            </tr>
            <tr>
                <td>Abonos</td>
                <td class="text-right">{{ $indicadores->getCantidadRecibos() }}</td>
                <td class="text-right">{{ $simbolo_moneda }}{{ number_format($indicadores->getTotalRecibos(), 2) }}</td></tr>
            <tr>
                <td>Devoluciones</td>
                <td class="text-right">{{ $indicadores->getCantidadDevolucionesVenta() }}</td>
                <td class="text-right">{{ $simbolo_moneda }}{{ number_format($indicadores->getTotalDevolucionesVenta(), 2) }}</td>
            </tr>
            @if (Auth::user()->tipo == 'Administrador')
            <tr><td>Gastos</td>
                <td class="text-right">{{ $indicadores->getCantidadGastos() }}</td>
                <td class="text-right">{{ $simbolo_moneda }}{{ number_format($indicadores->getTotalGastos(), 2) }}</td>
            </tr>
            @endif
            <tr>
                <td>Cuentas por cobrar</td>
                <td class="text-right">{{ $indicadores->getCantidadVentasPendientes() }}</td>
                <td class="text-right">{{ $simbolo_moneda }}{{ number_format($indicadores->getTotalVentasPendientes(), 2) }}</td>
            </tr>
            </tbody>
        </table>

        <h5 class="font-weight-bold text-uppercase my-3">
            <i class="fa-solid fa-square-pen"></i> Resumen de caja
        </h5>

        <table class="table table-bordered table-hover border-primary mb-3">
            <thead>
                <tr class="bg-primary text-white">
                    <th>Forma de pago</th>
                    <th class="text-right" width="20%">N° de transacciones</th>
                    <th class="text-right" width="17%">Total</th>
                </tr>
            </thead>
          <tbody>
            @foreach ($indicadores->getResumenCaja() as $formadepago)
                <tr class="bg-light font-weight-bold">
                    <td>{{ $formadepago['nombre'] }}</td> 
                    <td class="text-right">{{ $formadepago['cantidad'] }}</td>
                    <td class="text-right">{{ $simbolo_moneda }}{{ number_format($formadepago['total'],2) }}</td>
                </tr>     
            @endforeach
            </tbody>
        </table>

        <h5 class="font-weight-bold text-uppercase my-3">
            <i class="fa-solid fa-square-pen"></i> Resumen ventas por canal
        </h5>

        <table class="table table-bordered table-hover border-primary mb-3">
            <thead>
                <tr class="bg-primary text-white">
                    <th>Canal</th>
                    <th class="text-right" width="17%">Cantidad</th>
                    <th class="text-right" width="17%">Total</th>
                </tr>
            </thead>
          <tbody>
                @if (count($indicadores->getVentasByCanal()) == 0)
                    <tr>
                        <td colspan="3" class="text-muted text-center">
                            Ningún registro
                        </td>
                    </tr>
                @endif
                @foreach ($indicadores->getVentasByCanal() as $canal)
                <tr>
                    <td>{{$canal['nombre']}}</td>
                    <td class="text-right">{{ $canal['cantidad'] }}</td>
                    <td class="text-right">{{ $simbolo_moneda }}{{ number_format($canal['total'], 2) }}</td>
                </tr>
                @endforeach

            </tbody>
        </table>

        <h5 class="font-weight-bold text-uppercase my-3">
            <i class="fa-solid fa-square-pen"></i> Documentos emitidos
        </h5>

        <table class="table table-bordered table-hover border-primary mb-3">
            <thead>
                <tr class="bg-primary text-white">
                    <th>Documento</th>
                    <th class="text-right" width="17%">Correlativos</th>
                    <th class="text-right" width="17%">Total</th>
                </tr>
            </thead>
          <tbody>
                @if (count($indicadores->getDocumentoEmitidos()) == 0)
                    <tr>
                        <td colspan="3" class="text-muted text-center">
                            Ningún registro
                        </td>
                    </tr>
                @endif
                @foreach ($indicadores->getDocumentoEmitidos() as $documento)
                <tr>
                    <td>{{ $documento['nombre_sucursal'] }} - {{$documento['nombre']}}</td>
                    <td class="text-right">{{ $documento['inicio'] . ' - ' . $documento['fin']}} </td>
                    <td class="text-right">{{ $simbolo_moneda }}{{ number_format($documento['total'],2) }} </td>
                </tr>
                @endforeach

            </tbody>
        </table>

        <h5 class="font-weight-bold text-uppercase my-3">
            <i class="fa-solid fa-square-pen"></i> Documentos con devolución
        </h5>

        <table class="table table-bordered table-hover border-primary mb-3">
            <thead>
                <tr class="bg-primary text-white">
                    <th>Documento</th>
                    <th class="text-right" width="17%">Correlativo</th>
                    <th class="text-right" width="17%">Total</th>
                </tr>
            </thead>
          <tbody>
                @if ($indicadores->getDocumentoConDevolucion()->count() == 0)
                    <tr>
                        <td colspan="3" class="text-muted text-center">
                            Ningún registro
                        </td>
                    </tr>
                @endif
                @foreach ($indicadores->getDocumentoConDevolucion() as $devolucion)
                <tr>
                    <td>{{ $devolucion->venta()->first()->nombre_documento }}</td>
                    <td class="text-right">{{$devolucion->venta()->first()->correlativo}}</td>
                    <td class="text-right">{{ $simbolo_moneda }}{{number_format($devolucion->venta()->first()->total,2)}}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <h5 class="font-weight-bold text-uppercase my-3">
            <i class="fa-solid fa-square-pen"></i> Documentos anulados
        </h5>

        <table class="table table-bordered table-hover border-primary mb-3">
            <thead>
                <tr class="bg-primary text-white">
                    <th>Documento</th>
                    <th class="text-right" width="17%">Correlativo</th>
                    <th class="text-right" width="17%">Total</th>
                </tr>
            </thead>
          <tbody>
                @if ($indicadores->getDocumentosAnulados()->count() == 0)
                    <tr>
                        <td colspan="3" class="text-muted text-center">
                            Ningún registro
                        </td>
                    </tr>
                @endif
                @foreach ($indicadores->getDocumentosAnulados() as $venta)
                <tr>
                    <td>{{ $venta->nombre_documento }}</td>
                    <td class="text-right">{{ $venta->correlativo }}</td>
                    <td class="text-right">{{ $simbolo_moneda }}{{ number_format($venta->total,2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>


    </div>
    <center>
        <div id="footer">
            <p>SmartPyme Technologies S.A DE S.V</p>
            <a href="https://www.smartpyme.sv/">smartpyme.sv</a>
        </div>
    </center>
</body>
</html>
