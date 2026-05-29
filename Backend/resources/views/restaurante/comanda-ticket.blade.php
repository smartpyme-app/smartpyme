<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  <title>Comanda {{ $comanda->numero_comanda }}</title>
  <style>
    html, body { font-family: monospace; margin: 0; padding: 8px; font-size: 11px; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    hr { border: none; border-top: 1px dashed #333; margin: 4px 0; }
    table { width: 100%; border-collapse: collapse; }
    .no-print { display: none; }
    .etiq-elim { color: #b91c1c; font-weight: bold; }
  </style>
  <style media="print"> .no-print { display: none !important; } </style>
</head>
<body onload="window.print();">
  <div class="no-print" style="display:block; margin-bottom:8px;">
    <button onclick="window.print();">Imprimir</button>
    <button onclick="window.close();">Cerrar</button>
  </div>

  @php
    $dest = $comanda->destino ?? 'cocina';
    $motivoEtiquetas = [
        'error' => 'Error de carga',
        'rechazo_cliente' => 'Rechazo del cliente',
        'calidad' => 'Calidad / devolución',
        'otro' => 'Otro',
    ];
  @endphp

  <div class="text-center">
    <p><strong>{{ $empresa->nombre ?? 'Restaurante' }}</strong></p>
  </div>
  <hr>

  <p><strong>COMANDA: {{ $comanda->numero_comanda }}</strong></p>
  @if($dest === 'eliminacion')
    @if($comanda->eliminacion_item_enviado === false || $comanda->eliminacion_item_enviado === 0)
      <p class="etiq-elim">*** ELIMINADO — solo cuenta (no constaba envío a cocina/barra) ***</p>
    @else
      <p class="etiq-elim">*** ELIMINADO — ANULAR EN COCINA/BARRA ***</p>
    @endif
    @if($comanda->motivo_eliminacion_codigo)
      <p><strong>Motivo:</strong> {{ $motivoEtiquetas[$comanda->motivo_eliminacion_codigo] ?? $comanda->motivo_eliminacion_codigo }}</p>
    @endif
    @if(!empty($comanda->motivo_eliminacion_detalle))
      <p><em>Detalle: {{ $comanda->motivo_eliminacion_detalle }}</em></p>
    @endif
  @endif
  <p><strong>MESA: {{ $comanda->sesion->mesa->numero ?? '-' }}</strong></p>
  <p>Fecha: {{ $comanda->enviado_at ? $comanda->enviado_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}</p>
  @if($comanda->sesion->mesero)
  <p>Mesero: {{ $comanda->sesion->mesero->name ?? $comanda->sesion->mesero->email }}</p>
  @endif
  @if($comanda->sesion->observaciones)
  <p><em>Obs: {{ $comanda->sesion->observaciones }}</em></p>
  @endif
  <hr>

  <table>
    <thead>
      <tr>
        <th>Cant</th>
        <th>Producto</th>
      </tr>
    </thead>
    <tbody>
      @foreach($comanda->detalles as $det)
        @php
          $od = $det->ordenDetalle ?? null;
          $prod = $od ? ($od->producto ?? null) : null;
        @endphp
        @if($od)
        <tr>
          <td>{{ number_format($od->cantidad ?? 1, 0) }}x</td>
          <td>
            {{ $prod->nombre ?? 'Producto' }}
            @if(!empty($od->notas))
              <br><small><em>{{ $od->notas }}</em></small>
            @endif
          </td>
        </tr>
        @endif
      @endforeach
    </tbody>
  </table>

  <hr>
  <p class="text-center"><strong>
    @if($dest === 'eliminacion')
      --- ELIMINADO ---
    @elseif($dest === 'barra')
      --- BARRA ---
    @else
      --- COCINA ---
    @endif
  </strong></p>
</body>
</html>
