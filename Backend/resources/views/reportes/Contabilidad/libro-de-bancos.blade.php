<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Libro de bancos {{ $cuenta->nombre_banco }}</title>
    <style>

        *{
            margin: 0cm;
            font-family: 'system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue","Noto Sans","Liberation Sans",Arial,sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji"';
        }
        body {
            font-family: serif;
            margin: 50px;
        }
        h1,h2,h3,h4,h5,h6{
            color: #000 !important;
        }

        table{
            width: 100%;
            border-collapse: collapse;
        }
        .table th, .table td{
            border: 0px;
            border-collapse: collapse;
            padding: 10px 5px;
            text-align: left;
        }
        .text-center{
            text-align: center !important;
        }
        .text-right{
            text-align: right !important;
        }
        .border-bottom{
            border-bottom: 1px solid #000 !important;
        }


    </style>

</head>
<body>
{{-- <body onload="javascript:print();"> --}}

        <h3 class="text-center">{{ $cuenta->empresa()->pluck('nombre')->first() }}</h3>
        <h4 class="text-center">Registro del Libro de Bancos</h4>
        <h4 class="text-center">{{ $cuenta->nombre_banco }}</h4>
        <h4 class="text-center">{{ \Carbon\Carbon::parse($cuenta->del)->format('d/m/Y') }} {{ \Carbon\Carbon::parse($cuenta->al)->format('d/m/Y') }}</h4>
        <br>

        <table class="table">
            <thead>
                <tr>
                    <th class="border-bottom">Fecha</th>
                    <th class="border-bottom text-left">Concepto</th>
                    <th class="border-bottom text-right">Cargos</th>
                    <th class="border-bottom text-right">Abonos</th>
                    {{-- <th class="border-bottom text-right">Saldo</th> --}}
                </tr>
            </thead>
            <tbody>
                @foreach($cuenta->transacciones as $transaccion)
                <tr>
                    <td class="border-bottom"> {{ \Carbon\Carbon::parse($transaccion->fecha)->format('d/m/Y')  }}</td>
                    <td class="border-bottom"> {{ $transaccion->concepto  }}</td>
                    <td class="border-bottom text-right"> 
                        @if ($transaccion->tipo == 'Cargo')
                            ${{ number_format($transaccion->total, 2) }}
                        @endif
                    </td>
                    <td class="border-bottom text-right"> 
                        @if ($transaccion->tipo == 'Abono')
                            ${{ number_format($transaccion->total, 2) }}
                        @endif
                    </td>
                    {{-- <td class="border-bottom text-right"> 
                        ${{ number_format($transaccion->saldo, 2) }}
                    </th> --}}
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="1"></td>
                    <td class="text-right"><b>Totales:</b></td>
                    <td class="text-right"><b>${{ number_format($cuenta->transacciones->where('tipo', 'Cargo')->sum('total'), 2) }}</b></td>
                    <td class="text-right"><b>${{ number_format($cuenta->transacciones->where('tipo', 'Abono')->sum('total'), 2) }}</b></td>
                </tr>
                <tr>
                    <td colspan="1"></td>
                    <td colspan="2" class="text-right"><b>Saldo:</b></td>
                    <td class="text-right"><b>${{ number_format($cuenta->saldo, 2) }}</b></td>
                </tr>
            </tfoot>
        </table>


    </section>


</body>
</html>
