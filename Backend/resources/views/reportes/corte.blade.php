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

        <table class="table table-bordered table-hover border-primary mb-3">
            <tr>
                <td width="20%">
                    <h3 class="text-center text-success">
                        ${{ $indicadores->getTotalVentasPagadas() }}
                    </h3>
                    <p class="mb-0 text-center">TOTAL <br> VENTAS </p>
                </td>
                <td width="20%">
                    <h3 class="text-center text-info">
                        ${{ $indicadores->getTotalRecibos() }}
                    </h3>
                    <p class="mb-0 text-center">TOTAL <br> ABONOS</p>
                </td>
                <td width="20%">
                    <h3 class="text-center text-secondary">
                        ${{ $indicadores->getTotalVentasPendientes() }}
                    </h3>
                    <p class="mb-0 text-center">TOTAL <br> CREDITOS</p>
                </td>
                <td width="20%">
                    <h3 class="text-center text-danger">
                        ${{ $indicadores->getTotalDevolucionesVenta() }}
                    </h3>
                    <p class="mb-0 text-center">TOTAL <br> DEVOLUCIONES</p>
                </td>
                <td width="20%">
                    <h3 class="text-center text-secondary">
                        ${{ $indicadores->getTotalGastosPagados() }}
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
                    <th>Forma de pago</th>
                    <th class="text-right" width="20%">N° de transacciones</th>
                    <th class="text-right" width="17%">Total</th>
                </tr>
            </thead>
          <tbody>
            @foreach ($indicadores->getVentasByFormaPago() as $formadepago)
            <tr>
                <td>{{ $formadepago['nombre'] }} </td>
                <td class="text-right">{{ $formadepago['cantidad'] }}</td>
                <td class="text-right">${{ number_format($formadepago['total'],2) }}</td>
            </tr>
                @if ($formadepago['nombre'] == 'Tarjeta de crédito/débito')
                        @foreach ($indicadores->getVentasByBanco() as $bancos)
                        @if ($bancos['nombre'])
                            <tr>
                                <td><li class="ml-4">{{$bancos['nombre']}}</li> </td>
                                <td class="text-right">{{$bancos['cantidad']}} </td>
                                <td class="text-right"> ${{ number_format($bancos['total'],2) }}</td>
                            </tr>
                        @endif
                        @endforeach
                @endif
            @endforeach

            {{-- <tr><td>Abonos</td><td class="text-right">{{ $indicadores->getCantidadRecibos() }}</td><td class="text-right">${{ number_format($indicadores->getTotalRecibos(), 2) }}</td></tr> --}}
            <tr><td>Devoluciones</td><td class="text-right">{{ $indicadores->getCantidadDevolucionesVenta() }}</td><td class="text-right">${{ number_format($indicadores->getTotalDevolucionesVenta(), 2) }}</td></tr>
            {{-- <tr><td>Gastos</td><td class="text-right">{{ $indicadores->getCantidadGastosPagados() }}</td><td class="text-right">${{ number_format($indicadores->getTotalGastosPagados(), 2) }}</td></tr> --}}
            <tr><td>Cuentas por cobrar</td><td class="text-right">{{ $indicadores->getCantidadVentasPendientes() }}</td><td class="text-right">${{ number_format($indicadores->getTotalVentasPendientes(), 2) }}</td></tr>
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
            @if ($formadepago['total'] > 0)
                <tr class="bg-light font-weight-bold">
                    <td>{{ $formadepago['nombre'] }}</td> 
                    <td class="text-right">{{ $formadepago['cantidad'] }}</td>
                    <td class="text-right">${{ number_format($formadepago['total'],2) }}</td>
                </tr> 
                <tr>
                    <td><li class="ml-4">Ventas</li> </td> 
                    <td class="text-right">{{ $indicadores->ventas_pagadas->where('forma_pago', $formadepago['nombre'])->count() - $indicadores->devoluciones_ventas->where('forma_pago', $formadepago['nombre'])->count() }}</td>
                    <td class="text-right">${{ number_format($indicadores->ventas_pagadas->where('forma_pago', $formadepago['nombre'])->sum('saldo') - $indicadores->devoluciones_ventas->where('forma_pago', $formadepago['nombre'])->sum('total'), 2) }}</td>
                </tr>
                <tr>
                    <td><li class="ml-4">Abonos</li> </td> 
                    <td class="text-right">{{ $indicadores->recibos->where('forma_pago', $formadepago['nombre'])->count() }}</td>
                    <td class="text-right">${{ number_format($indicadores->recibos->where('forma_pago', $formadepago['nombre'])->sum('monto'), 2) }}</td>
                </tr> 
                <tr>
                    <td><li class="ml-4">Gastos</li> </td> 
                    <td class="text-right">{{ $indicadores->gastos->where('forma_pago', $formadepago['nombre'])->count() }}</td>
                    <td class="text-right">${{ number_format($indicadores->gastos->where('forma_pago', $formadepago['nombre'])->sum('monto'), 2) }}</td>
                </tr>         
            @endif
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
                    <td class="text-right">${{ number_format($canal['total'], 2) }}</td>
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
                    <td class="text-right">{{ substr($documento['nombre'], 0, 1) . $documento['inicio'] . ' - ' . substr($documento['nombre'], 0, 1) . $documento['fin']}} </td>
                    <td class="text-right">${{ number_format($documento['total'],2) }} </td>
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
                    <td>{{ $devolucion->venta()->first()->sucursal }} - {{$devolucion->venta()->first()->documento}}</td>
                    <td class="text-right">{{$devolucion->venta()->first()->correlativo}}</td>
                    <td class="text-right">${{number_format($devolucion->venta()->first()->total_venta,2)}}</td>
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
                    <td>{{ $venta->sucursal }} - {{$venta->documento}}</td>
                    <td class="text-right">{{$venta->correlativo}}</td>
                    <td class="text-right">${{number_format($venta->total_venta,2)}}</td>
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
