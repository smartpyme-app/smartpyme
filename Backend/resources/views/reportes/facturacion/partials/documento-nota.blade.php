@php
    $empresaImpresion = $empresa ?? (isset($venta) ? $venta->empresa : null);
    $mostrarNotaDocumento = $empresaImpresion
        && method_exists($empresaImpresion, 'mostrarNotaDocumentoImpresion')
        && $empresaImpresion->mostrarNotaDocumentoImpresion();
@endphp
@if ($mostrarNotaDocumento && !empty($documento?->nota))
    <div class="documento-nota" style="font-size: 8px; text-align: center; margin-top: 4px; line-height: 1.15; padding: 0 4px; word-break: break-word;">
        {!! nl2br(e($documento->nota)) !!}
    </div>
@endif
