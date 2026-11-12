@php
    $mostrar = !empty($mostrar_lotes_impresion);
    $lote = ($detalle->relationLoaded('lote') && $detalle->lote) ? $detalle->lote : null;
@endphp
@if ($mostrar && $lote && ($lote->numero_lote || $lote->fecha_vencimiento))
    <div style="font-size: 7pt; color: #444; margin-top: 1px;">
        Lote: {{ $lote->numero_lote ?? '—' }}
        @if ($lote->fecha_vencimiento)
            · Vence: {{ \Carbon\Carbon::parse($lote->fecha_vencimiento)->format('d/m/Y') }}
        @endif
    </div>
@endif
