<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  {{-- <script language="javascript">setTimeout("self.close();",1000)</script> --}}
  <title>Factura</title>
  <style>
    h1, h2, h3{
        margin: 3pt;
    }
    html, body {
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI",
    "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Fira Sans",
    "Droid Sans", "Helvetica Neue", sans-serif;
        margin: 0pt;
        padding: 0pt;
        font-size: 9pt;
        text-align: center;
    }
    hr { border: none; height: 2px; /* Set the hr color */ color: #000; /* old IE */ background-color: #333; /* Modern Browsers */ }

    p{ margin: 0px; };
    table{width: 100%; margin: auto; text-align: left; border-collapse: collapse;}
    table td{height: 12pt;}
    .text-center { text-align: center; }
    .text-right { text-align: right; }
  </style>
  
  <style media="print"> .no-print{display: none; } </style>

</head>
{{-- <body onload="javascript:print();"> --}}
<body>
    @php $simbolo_moneda = optional($empresa->currency)->currency_symbol ?? '$'; @endphp

    <button class="no-print tcla-c" onClick="window.close();" autofocus>Cerrar (C)</button>
    <button class="no-print tcla-p" onClick="javascript:print();" autofocus>Imprimir (P)</button>
  
    <h3>Corte Z</h3>
    <h3 class="text-center">{{ $empresa->nombre }}</h3>
    <p class="text-center">{{ $empresa->sector }}</p>
    <p>{{ $empresa->propietario }}</p>
    <p>{{ $empresa->direccion }}</p>
    <p><b>@if($empresa->pais == 'El Salvador')NIT:@else Identificación fiscal:@endif</b> {{ $empresa->nit }}</p> 
    <p><b>@if($empresa->pais == 'El Salvador')NCR:@else Registro tributario:@endif</b> {{ $empresa->ncr }} </p>
    <p><b>GIRO:</b> {{ $empresa->giro }}</p>
    <p><b>TELÉFONO:</b> {{ $empresa->telefono }}</p>

    <p><b>Fecha:</b> {{ $corte->cierre ? \Carbon\Carbon::parse($corte->cierre)->format('d/m/Y H:m a') : \Carbon\Carbon::now()->format('d/m/Y H:m a') }}</p>
    <p>CAJA 1 </p>
    Corte # {{ $corte->id }}
       
    <hr>
    <p>VENTAS TICKETS </p>
    <p>Del &nbsp;&nbsp;&nbsp;&nbsp; Al </p>
    <p>{{ $corte->tickets_rango }} </p>
    <p><b>TOTAL: {{ $simbolo_moneda }}{{ number_format($corte->tickets, 2) }}</b></p>

    <hr>
    <p>VENTAS FACTURAS </p>
    <p>Del &nbsp;&nbsp;&nbsp;&nbsp; Al </p>
    <p>{{ $corte->facturas_rango }} </p>
    <p><b>TOTAL: {{ $simbolo_moneda }}{{ number_format($corte->facturas, 2) }}</b></p>
    <hr>

    <p>VENTAS CRÉDITOS FISCALES </p>
    <p>Del &nbsp;&nbsp;&nbsp;&nbsp; Al </p>
    <p>{{ $corte->creditos_fiscales_rango }} </p>
    <p><b>TOTAL: {{ $simbolo_moneda }}{{ number_format($corte->creditos_fiscales, 2) }}</b></p>
    <hr>

    <p>VENTAS NOTAS DE CREDITO </p>
    <p>Del &nbsp;&nbsp;&nbsp;&nbsp; Al </p>
    <p>{{ $corte->notas_credito_rango }} </p>
    <p><b>TOTAL: {{ $simbolo_moneda }}{{ number_format($corte->notas_creditos, 2) }}</b></p>
    <hr>

    <p>VENDEDORES:</p>
    @foreach ($corte->usuarios as $usuario)
      <p>{{ $usuario->name }}</p>
    @endforeach

    <hr>
    FORMAS DE PAGO
    <table style="margin: auto;">
      <tr><td>EFECTIVO:</td> <td>{{ $simbolo_moneda }}{{ number_format($corte->ventas_efectivo,2 ) }}</td> </tr>
      <tr><td>TARJETA:</td> <td>{{ $simbolo_moneda }}{{ number_format($corte->ventas_tarjeta,2 ) }}</td> </tr>
      <tr><td>TOTAL:</td> <td>{{ $simbolo_moneda }}{{ number_format($corte->ventas_total,2 ) }}</td> </tr>
    </table>
    <hr>

    TOTALES
    <table style="margin: auto;">
      <tr><td>SUB TOTAL:</td> <td>{{ $simbolo_moneda }}{{ number_format($corte->subtotal,2 ) }}</td> </tr>
      <tr><td>EXENTA:</td> <td>{{ $simbolo_moneda }}{{ number_format($corte->exenta,2 ) }}</td> </tr>
      <tr><td>NO SUJETA:</td> <td>{{ $simbolo_moneda }}{{ number_format($corte->no_sujeta,2 ) }}</td> </tr>
      <tr><td>IVA:</td> <td>{{ $simbolo_moneda }}{{ number_format($corte->iva,2 ) }}</td> </tr>
      {{-- <tr><td>PERCEPCION:</td> <td>{{ $simbolo_moneda }}{{ number_format($corte->iva_retenido,2 ) }}</td> </tr> --}}
      <tr><td>RETENCION:</td> <td>{{ $simbolo_moneda }}{{ number_format($corte->iva_retenido,2 ) }}</td> </tr>
      <tr><td>SALDO INICIAL:</b></td> <td><b>{{ $simbolo_moneda }}{{ number_format($corte->saldo_inicial,2 ) }}</td> </tr>
      <tr><td><b>TOTAL:</b></td> <td><b>{{ $simbolo_moneda }}{{ number_format($corte->ventas_total,2 ) }}</b></td> </tr>
    </table>
    <br><br><br><br>
    <p>.</p>

    <script>
      window.onload = function() {
        document.addEventListener('keypress', function(tecla){
            let letra;
            letra = 'tcla-' + tecla.key;
            let button = document.getElementsByClassName(letra)[0];
            button.click()
            // Prevenir eventos de tecla F1 - F12
            if (tecla.keyCode <= 123 && tecla.keyCode >= 112) {
                tecla.preventDefault();
            }

        });
      }
    </script>

</body>
</html>