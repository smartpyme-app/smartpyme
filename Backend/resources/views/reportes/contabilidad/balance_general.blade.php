<!DOCTYPE html>
<html>
<head>
    <title>Balance General</title>
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

        #balance {
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

        .balance-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        .balance-table td {
            vertical-align: top;
            width: 50%;
            padding: 0 10px;
        }

        .section-title {
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 10px;
            text-decoration: underline;
            text-align: center;
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

        .section-break {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<section id="balance">
    <div class="header">
        <h1>{{ $empresa->nombre }}</h1>
        <h2>BALANCE GENERAL</h2>
        <h3>Al {{ $month_name }} de {{ $year }}</h3>
        <h3>(Expresado en US Dólares)</h3>
    </div>

    <table class="balance-table">
        <tr>
            <!-- COLUMNA IZQUIERDA: ACTIVOS -->
            <td>
                <div class="section-title">ACTIVOS</div>

                @foreach($balance_general['activos'] as $activo)
                    <div class="account-line">
                        <span class="account-name">{{ $activo['nombre'] }}</span>
                        <span class="account-amount">{{ number_format(abs($activo['saldo_final']), 2) }}</span>
                    </div>
                @endforeach

                <div class="total-line account-line">
                    <span class="account-name">TOTAL ACTIVOS</span>
                    <span class="account-amount">{{ number_format(abs($balance_general['totales']['activos']), 2) }}</span>
                </div>
            </td>

            <!-- COLUMNA DERECHA: PASIVOS Y PATRIMONIO -->
            <td>
                <!-- PASIVOS -->
                <div class="section-title">PASIVOS</div>

                @foreach($balance_general['pasivos'] as $pasivo)
                    <div class="account-line">
                        <span class="account-name">{{ $pasivo['nombre'] }}</span>
                        <span class="account-amount">{{ number_format(abs($pasivo['saldo_final']), 2) }}</span>
                    </div>
                @endforeach

                @if(count($balance_general['pasivos']) > 0)
                    <div class="subtotal-line account-line">
                        <span class="account-name">TOTAL PASIVOS</span>
                        <span class="account-amount">{{ number_format(abs($balance_general['totales']['pasivos']), 2) }}</span>
                    </div>
                @endif

                <div class="section-break"></div>

                <!-- PATRIMONIO -->
                <div class="section-title">PATRIMONIO</div>

                @foreach($balance_general['patrimonio'] as $patrimonio)
                    <div class="account-line">
                        <span class="account-name">{{ $patrimonio['nombre'] }}</span>
                        <span class="account-amount">{{ number_format(abs($patrimonio['saldo_final']), 2) }}</span>
                    </div>
                @endforeach

                @if(count($balance_general['patrimonio']) > 0)
                    <div class="subtotal-line account-line">
                        <span class="account-name">TOTAL PATRIMONIO</span>
                        <span class="account-amount">{{ number_format(abs($balance_general['totales']['patrimonio']), 2) }}</span>
                    </div>
                @endif

                <div class="total-line account-line">
                    <span class="account-name">TOTAL PASIVOS + PATRIMONIO</span>
                    <span class="account-amount">{{ number_format(abs($balance_general['totales']['pasivos'] + $balance_general['totales']['patrimonio']), 2) }}</span>
                </div>
            </td>
        </tr>
    </table>

</section>

</body>
</html>
