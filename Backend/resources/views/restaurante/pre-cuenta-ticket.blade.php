<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  <title>Pre-cuenta {{ $preCuenta->numero_pre_cuenta }}</title>
  <style>
    html, body { font-family: monospace; margin: 0; padding: 8px; font-size: 11px; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    hr { border: none; border-top: 1px dashed #333; margin: 4px 0; }
    table { width: 100%; border-collapse: collapse; }
    .no-print { display: none; }
  </style>
  <style media="print"> .no-print { display: none !important; } </style>
</head>
<body onload="window.print();">
  <div class="no-print" style="display:block; margin-bottom:8px;">
    <button onclick="window.print();">Imprimir</button>
    <button onclick="window.close();">Cerrar</button>
  </div>

  <div class="text-center">
    <p><strong>{{ $empresa->nombre ?? 'Restaurante' }}</strong></p>
  </div>
  <hr>

  <p><strong>PRE-CUENTA: {{ $preCuenta->numero_pre_cuenta }}</strong></p>
  <p><strong>MESA: {{ $preCuenta->sesion->mesa->numero ?? '-' }}</strong></p>
  <p>Fecha: {{ now()->format('d/m/Y H:i') }}</p>
  @if($preCuenta->sesion->mesero)
  <p>Mesero: {{ $preCuenta->sesion->mesero->name ?? $preCuenta->sesion->mesero->email }}</p>
  @endif
  @if($preCuenta->sesion->observaciones)
  <p><em>Obs: {{ $preCuenta->sesion->observaciones }}</em></p>
  @endif
  <hr>

  <table>
    <thead>
      <tr>
        <th>Cant</th>
        <th>Producto</th>
        <th class="text-right">Precio</th>
        <th class="text-right">Subtotal</th>
      </tr>
    </thead>
    <tbody>
      @foreach($items as $od)
        @php $prod = $od->producto ?? null; $sub = ($od->cantidad ?? 1) * ($od->precio_unitario ?? 0); @endphp
        <tr>
          <td>{{ number_format($od->cantidad ?? 1, 0) }}x</td>
          <td>
            {{ $prod->nombre ?? 'Producto' }}
            @if(!empty($od->notas))
              <br><small><em>{{ $od->notas }}</em></small>
            @endif
          </td>
          <td class="text-right">{{ number_format($od->precio_unitario ?? 0, 2) }}</td>
          <td class="text-right">{{ number_format($sub, 2) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <hr>
  <div class="text-right">
    <p><strong>TOTAL: {{ number_format($preCuenta->total ?? 0, 2) }}</strong></p>
  </div>
  <hr>
  <p class="text-center"><strong>--- GRACIAS POR SU VISITA ---</strong></p>
</body>
</html>
