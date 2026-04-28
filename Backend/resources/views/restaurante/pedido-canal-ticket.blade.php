<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  <title>Pedido #{{ $pedido->id }}</title>
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
    <button type="button" onclick="window.print();">Imprimir</button>
    <button type="button" onclick="window.close();">Cerrar</button>
  </div>

  <div class="text-center">
    <p><strong>{{ $empresa->nombre ?? 'Empresa' }}</strong></p>
  </div>
  <hr>

  <p><strong>PEDIDO CANAL #{{ $pedido->id }}</strong></p>
  <p>Estado: {{ $pedido->estado }}</p>
  <p>Fecha pedido: {{ $pedido->fecha?->format('d/m/Y') }}</p>
  <p>Impresión: {{ now()->format('d/m/Y H:i') }}</p>
  @if($pedido->canal)
  <p><strong>Canal:</strong> {{ $pedido->canal }}</p>
  @endif
  @if($pedido->referencia_externa)
  <p><strong>Ref. externa:</strong> {{ $pedido->referencia_externa }}</p>
  @endif
  @if($pedido->cliente)
  <p>
    <strong>Cliente:</strong>
    {{ $pedido->cliente->nombre_empresa ?: $pedido->cliente->nombre_completo }}
  </p>
  @php
    $c = $pedido->cliente;
    $dirPedido = trim((string) ($c->direccion ?: $c->empresa_direccion ?: ''));
    $telPedido = trim((string) ($c->telefono ?: $c->empresa_telefono ?: ''));
  @endphp
  @if($dirPedido !== '')
  <p><strong>Dirección:</strong> {{ $dirPedido }}</p>
  @endif
  @if($telPedido !== '')
  <p><strong>Tel.:</strong> {{ $telPedido }}</p>
  @endif
  @endif
  @if($pedido->usuario)
  <p>Usuario: {{ $pedido->usuario->name ?? $pedido->usuario->email }}</p>
  @endif
  @if($pedido->observaciones)
  <p><em>Obs: {{ $pedido->observaciones }}</em></p>
  @endif
  <hr>

  <table>
    <thead>
      <tr>
        <th>Cant</th>
        <th>Producto</th>
        <th class="text-right">P. unit.</th>
        <th class="text-right">Total</th>
      </tr>
    </thead>
    <tbody>
      @foreach($pedido->detalles as $d)
        @php $prod = $d->producto ?? null; @endphp
        <tr>
          <td>{{ number_format((float) $d->cantidad, 2) }}</td>
          <td>
            {{ $prod->nombre ?? 'Producto' }}
            @if(!empty($d->notas))
              <br><small><em>{{ $d->notas }}</em></small>
            @endif
          </td>
          <td class="text-right">{{ number_format((float) $d->precio, 2) }}</td>
          <td class="text-right">{{ number_format((float) $d->total, 2) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <hr>
  <div class="text-right">
    <p>Subtotal: {{ number_format((float) $pedido->subtotal, 2) }}</p>
    @if((float) $pedido->descuento > 0)
    <p>Descuento: -{{ number_format((float) $pedido->descuento, 2) }}</p>
    @endif
    <p><strong>TOTAL: {{ number_format((float) $pedido->total, 2) }}</strong></p>
  </div>
  <hr>
  <p class="text-center"><strong>--- PEDIDO CANAL ---</strong></p>
</body>
</html>
