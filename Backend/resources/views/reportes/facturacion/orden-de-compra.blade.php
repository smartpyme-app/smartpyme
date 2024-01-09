<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Orden de Compra</title>
    <style>

        *{ margin: 0cm; padding: 0cm;}
        body {
            font-family: serif;
            margin: 50px;
        }

        table{
            width: 100%;
            border-collapse: collapse;
        }
        .table th, .table td{
            border: 1px solid #555;
            border-collapse: collapse;
            padding: 5px;
            text-align: left;
        }
        .text-right{
            text-align: right !important;
        }


    </style>
    
</head>
<body>
{{-- <body onload="javascript:print();"> --}}

        <table>
            <tbody>
                <tr>
                    <td>
                        <h1>{{ $compra->empresa()->pluck('nombre')->first() }}</h1>
                        <h4>{{ $compra->empresa()->pluck('direccion')->first() }}</h4>
                        <h4>{{ $compra->empresa()->pluck('telefono')->first() }}</h4>
                    </td>
                    <td>
                        <p class="text-right">#{{ $compra->id }}</p>
                        <p class="text-right">Fecha: {{ \Carbon\Carbon::parse($compra->fecha)->format('d/m/Y') }}</p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <br><br>

        <table>
            <tbody>
                <tr>
                    <td><h2>Proveedor</h2></td>
                </tr>
                <tr>
                    <td>
                        <p>{{ $compra->proveedor()->pluck('nombre')->first() }}</p>
                        <h4>{{ $compra->proveedor()->pluck('direccion')->first() }}</h4>
                        <h4>{{ $compra->proveedor()->pluck('telefono')->first() }}</h4>
                    </td>
                </tr>
            </tbody>
        </table> 

        <br><br>

        <table class="table">
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th class="text-right">Cantidad</th>
                    <th class="text-right">Precio</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($compra->detalles as $detalle)
                <tr>
                    <td>   {{ $detalle->nombre_producto  }}</td>
                    <td class="text-right">   {{ number_format($detalle->cantidad, 0) }}</td>
                    <td class="text-right">   ${{number_format($detalle->costo , 2) }}</td>
                    <td class="text-right">   ${{ number_format($detalle->total, 2) }}</th>
                </tr>
                @if ($detalle->descuento > 0)
                    <tr>
                        <td>DESCUENTOS</td>
                        <td></td>
                        <td></td>
                        <td class="text-right">- ${{ number_format($detalle->descuento, 2) }} </th>
                    </tr>
                @endif
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2"></td>
                    <td class="text-right">Sumas</td>
                    <td class="text-right">${{ number_format($compra->sub_total, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="2"></td>
                    <td class="text-right">IVA</td>
                    <td class="text-right">${{ number_format($compra->iva, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="2"></td>
                    <td class="text-right">Subtotal</td>
                    <td class="text-right">${{ number_format($compra->sub_total + $compra->iva, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="2"></td>
                    <td class="text-right"><b>Total</b></td>
                    <td class="text-right"><b>${{ number_format($compra->total, 2) }}</b></td>
                </tr>
            </tfoot>
        </table>

    </section>


</body>
</html>
