{{-- Variables: $detalle, $cotizacion_mostrar_descripcion, $cotizacion_mostrar_imagenes_productos --}}
@php
    $mostrarDesc = $cotizacion_mostrar_descripcion ?? true;
    $mostrarImg = $cotizacion_mostrar_imagenes_productos ?? false;
    $ambosActivos = $mostrarDesc && $mostrarImg;

    $rawDesc = $detalle->getRawOriginal('descripcion');
    $tieneDescripcionPersonalizada = $rawDesc !== null && $rawDesc !== '';

    $lineaTexto = $mostrarDesc
        ? $detalle->nombre_producto
        : (
            $tieneDescripcionPersonalizada && $detalle->producto
                ? $detalle->producto->nombre
                : $detalle->nombre_producto
          );

    $imgRel = $mostrarImg ? ($detalle->img ?? null) : null;
    if ($imgRel && (strpos($imgRel, 'default') !== false)) {
        $imgRel = null;
    }
    $imgAbs = $imgRel ? public_path('img/' . $imgRel) : null;
    // $imgOk = $imgAbs && @file_exists($imgAbs); comentamos el anterior

    
    //aqui empieza el nuevo codigo
    // DomPDF suele cargar bien URLs remotas (como asset() del logo) pero falla con rutas
    // absolutas según chroot/open_basedir. Data URI evita HTTP y rutas en el HTML.
    $imgSrc = null;
    if ($imgAbs && is_file($imgAbs) && is_readable($imgAbs)) {
        $ext = strtolower(pathinfo($imgAbs, PATHINFO_EXTENSION));
        $mimePorExt = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'webp' => 'image/webp',
        ];
        $mime = $mimePorExt[$ext] ?? null;
        if (! $mime && function_exists('mime_content_type')) {
            $detectado = @mime_content_type($imgAbs);
            if ($detectado && stripos($detectado, 'image/') === 0) {
                $mime = $detectado;
            }
        }
        $mime = $mime ?: 'image/jpeg';
        $binario = @file_get_contents($imgAbs);
        if ($binario !== false && $binario !== '') {
            $imgSrc = 'data:' . $mime . ';base64,' . base64_encode($binario);
        }
    }
    $imgOk = $imgSrc !== null;
    // aqui termina el nuevo codigo

    $producto = $detalle->producto;
    $textoDetalleProducto = '';
    if ($producto && $ambosActivos) {
        $textoDetalleProducto = trim((string) ($producto->descripcion_completa ?? ''));
        if ($textoDetalleProducto === '') {
            $textoDetalleProducto = trim((string) ($producto->descripcion ?? ''));
        }
        if ($textoDetalleProducto === '' && $tieneDescripcionPersonalizada) {
            $textoDetalleProducto = trim((string) $rawDesc);
        }
    }

    $variacionLinea = '';
    if ($producto && $ambosActivos) {
        if (!empty($producto->shopify_variant_id)) {
            $variacionLinea = 'Variación: ' . $producto->shopify_variant_id;
        } elseif (!empty($producto->woocommerce_id)) {
            $variacionLinea = 'Variación: ' . $producto->woocommerce_id;
        } elseif (!empty($producto->nombre_variante)) {
            $variacionLinea = 'Variación: ' . $producto->nombre_variante;
        }
    }
@endphp

@if($ambosActivos && $producto)
    <table style="width: 100%; border: none; border-collapse: collapse; margin: 0;">
        <tr>
            <td style="width: 76px; vertical-align: top; border: none; padding: 0 12px 0 0;">
                @if($imgOk)
                {{-- codigo anterior: <img src="{{ $imgAbs }}" alt=""  --}}
                    <img src="{{ $imgSrc }}" alt=""
                         style="display: block; width: 64px; height: 64px; object-fit: contain; border: 1px solid #c8c8c8; padding: 2px; background: #fff;">
                @endif
            </td>
            <td style="vertical-align: top; border: none; padding: 0;">
                <div style="font-weight: 700; text-transform: uppercase; font-size: 11px; line-height: 1.3; margin-bottom: 4px;">
                    {{ $producto->nombre }}
                </div>
                @if($variacionLinea !== '')
                    <div style="font-size: 10px; line-height: 1.35; margin-bottom: 6px; color: #222;">
                        {{ $variacionLinea }}
                    </div>
                @endif
                @if($textoDetalleProducto !== '')
                    <div style="font-weight: normal; font-size: 10px; line-height: 1.45; color: #222;">
                        {!! nl2br(e($textoDetalleProducto)) !!}
                    </div>
                @endif
            </td>
        </tr>
    </table>
@else
    @if($imgOk)
      {{-- codigo anterior: <img src="{{ $imgAbs }}" alt="" --}}
        <img src="{{ $imgSrc }}" alt=""
             style="float: left; max-width: 56px; max-height: 56px; object-fit: contain; margin-right: 10px; margin-bottom: 4px;">
    @endif
    {{ $lineaTexto }}
    @if($imgOk)
        <br style="clear: both;">
    @endif
@endif
