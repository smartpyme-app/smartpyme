<!DOCTYPE html>
<html>
<head>
    <title>Estado de Resultados</title>
    <style>
        * {
            font-size: 11px;
            margin: 0;
            padding: 0;
        }

        html, body {
            width: 19.5cm;
            height: 26cm;
            font-family: Arial, sans-serif;
        }

        #resultados {
            margin: 1cm;
            position: relative;
        }

        .header {
            text-align: center;
            font-weight: bold;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 16px;
            margin-bottom: 8px;
        }

        .header h2 {
            font-size: 14px;
            margin-bottom: 5px;
        }

        .header h3 {
            font-size: 12px;
            margin-bottom: 3px;
        }

        .resultados-container {
            width: 100%;
            margin-bottom: 25px;
        }

        .section-title {
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 10px;
            margin-top: 15px;
            text-decoration: underline;
        }

        .account-line {
            margin-bottom: 3px;
            padding: 2px 0;
        }

        .account-name {
            display: inline-block;
            width: 70%;
            text-align: left;
        }

        .account-amount {
            display: inline-block;
            width: 28%;
            text-align: right;
        }

        .total-line {
            border-top: 2px solid black;
            border-bottom: 2px solid black;
            font-weight: bold;
            margin-top: 10px;
            padding: 5px 0;
        }

        .subtotal-line {
            border-top: 1px solid black;
            font-weight: bold;
            margin-top: 8px;
            padding: 3px 0;
        }

        .utilidad-line {
            border-top: 3px double black;
            border-bottom: 3px double black;
            font-weight: bold;
            margin-top: 15px;
            padding: 8px 0;
            font-size: 12px;
        }

        .utilidad-positiva {
            color: #006600;
        }

        .utilidad-negativa {
            color: #cc0000;
        }

        .section-break {
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

<section id="resultados">
    <div class="header">
        <h1>{{ $empresa->nombre }}</h1>
        <h2>ESTADO DE RESULTADOS</h2>
        <h3>Del {{ \Carbon\Carbon::parse($fecha_inicio)->format('d') }} al {{ \Carbon\Carbon::parse($fecha_fin)->format('d') }} de {{ $month_name }} de {{ $year }}</h3>
        <h3>(Expresado en US Dólares)</h3>
    </div>

    <div class="resultados-container">
        <!-- INGRESOS -->
        <div class="section-title">INGRESOS</div>

        @foreach($estado_resultados['ingresos'] as $ingreso)
            <div class="account-line">
                <span class="account-name">{{ $ingreso['nombre'] }}</span>
                <span class="account-amount">{{ number_format(abs($ingreso['saldo_final']), 2) }}</span>
            </div>
        @endforeach

        @if(count($estado_resultados['ingresos']) > 0)
            <div class="subtotal-line account-line">
                <span class="account-name">TOTAL INGRESOS</span>
                <span class="account-amount">{{ number_format($estado_resultados['totales']['ingresos'], 2) }}</span>
            </div>
        @endif

        <div class="section-break"></div>

        <!-- COSTOS Y GASTOS -->
        <div class="section-title">COSTOS Y GASTOS</div>

        @foreach($estado_resultados['costos_gastos'] as $costo_gasto)
            <div class="account-line">
                <span class="account-name">{{ $costo_gasto['nombre'] }}</span>
                <span class="account-amount">{{ number_format(abs($costo_gasto['saldo_final']), 2) }}</span>
            </div>
        @endforeach

        @if(count($estado_resultados['costos_gastos']) > 0)
            <div class="subtotal-line account-line">
                <span class="account-name">TOTAL COSTOS Y GASTOS</span>
                <span class="account-amount">{{ number_format($estado_resultados['totales']['costos_gastos'], 2) }}</span>
            </div>
        @endif

        <div class="section-break"></div>

        <!-- UTILIDAD/PÉRDIDA -->
        <div class="utilidad-line account-line {{ $estado_resultados['totales']['utilidad_perdida'] >= 0 ? 'utilidad-positiva' : 'utilidad-negativa' }}">
            <span class="account-name">
                {{ $estado_resultados['totales']['utilidad_perdida'] >= 0 ? 'UTILIDAD DEL PERÍODO' : 'PÉRDIDA DEL PERÍODO' }}
            </span>
            <span class="account-amount">{{ number_format(abs($estado_resultados['totales']['utilidad_perdida']), 2) }}</span>
        </div>
    </div>

</section>

</body>
</html>
