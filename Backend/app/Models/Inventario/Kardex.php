<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class Kardex extends Model {

    protected $table = 'kardexs';
    protected $fillable = array(
        'fecha',
        'id_producto',
        'lote_id',
        'id_inventario',
        'detalle',
        'referencia',
        'entrada_cantidad',
        'costo_unitario',
        'entrada_valor',
        'salida_cantidad',
        'precio_unitario',
        'salida_valor',
        'total_cantidad',
        'total_valor',
        'id_usuario',
    );

    protected $appends = ['nombre_usuario', 'nombre_producto', 'modelo', 'modelo_detalle', 'numero_lote', 'nombre_proveedor_origen', 'nacionalidad_proveedor'];

    public function getNombreUsuarioAttribute()
    {
        return $this->usuario()->first() ? $this->usuario()->pluck('name')->first() : '';
    }

    public function getNombreProductoAttribute()
    {
        return  $this->producto()->first() ? $this->producto()->pluck('nombre')->first() : '';
    }

    public function getModeloDetalleattribute(){
        $detalle = [];
        $info = '';
        if ($this->detalle == 'Venta' || $this->detalle == 'Venta a consigna' || $this->detalle == 'Venta Anulada') {
            $detalle = \App\Models\Ventas\Venta::find($this->referencia);
            if ($detalle) {
                $nombre = $detalle->nombre_documento ?? 'Venta';
                $info = $detalle->correlativo ? ($nombre . ' #' . $detalle->correlativo) : $nombre;
            } else {
                $info = 'Venta #' . $this->referencia;
            }
        }
        if (str_contains($this->detalle, 'Devolución Venta')) {
            $detalle = \App\Models\Ventas\Devoluciones\Devolucion::find($this->referencia);
            if ($detalle) {
                $nombre = $detalle->nombre_documento ?: 'Devolución';
                $info = $detalle->correlativo ? ($nombre . ' #' . $detalle->correlativo) : $nombre;
            } else {
                $info = 'Devolución';
            }
        }
        if ($this->detalle == 'Compra' || $this->detalle == 'Compra a consigna' || $this->detalle == 'Compra Anulada') {
            $detalle = \App\Models\Compras\Compra::find($this->referencia);
            if ($detalle) {
                $nombre = $detalle->tipo_documento ?? 'Compra';
                $info = $detalle->referencia ? ($nombre . ' #' . $detalle->referencia) : $nombre;
            } else {
                $info = 'Compra #' . $this->referencia;
            }
        }
        if (str_contains($this->detalle, 'Devolución Compra')) {
            $detalle = \App\Models\Compras\Devoluciones\Devolucion::find($this->referencia);
            if ($detalle) {
                $nombre = $detalle->tipo_documento ?: 'Devolución';
                $info = $detalle->referencia ? ($nombre . ' #' . $detalle->referencia) : $nombre;
            } else {
                $info = 'Devolución';
            }
        }
        if (strpos($this->detalle , 'Traslado') !== false || strpos($this->detalle , 'traslado') !== false) {
            $detalle = \App\Models\Inventario\Traslado::find($this->referencia);
            $info = 'Traslado';
        }
        if (strpos($this->detalle , 'Ajuste') !== false || strpos($this->detalle , 'ajuste') !== false) {
            $detalle = \App\Models\Inventario\Ajuste::find($this->referencia);
            $info = 'Ajuste';
        }
        if ($this->detalle == 'Actualización de producto' || $this->detalle == 'Actualización de producto desde Shopify') {
            $info = $this->detalle;
        }
        if ($this->detalle == 'Otra Entrada' || $this->detalle == 'Otra Entrada Anulada') {
            $info = 'Entrada #' . $this->referencia;
        }
        if ($this->detalle == 'Otra Salida' || $this->detalle == 'Otra Salida Anulada') {
            $info = 'Salida #' . $this->referencia;
        }

        return $info;
    }

    public function getModeloattribute(){
        if ($this->detalle == 'Venta' || $this->detalle == 'Venta a consigna' || $this->detalle == 'Venta Anulada') {
            return 'venta';
        }
        if ($this->detalle == 'Devolución Venta' || $this->detalle == 'Devolución Venta Anulada') {
            return 'devolucion/venta';
        }
        if ($this->detalle == 'Devolución Compra' || $this->detalle == 'Devolución Compra Anulada') {
            return 'devolucion/compra';
        }
        if ($this->detalle == 'Compra' || $this->detalle == 'Compra a consigna' || $this->detalle == 'Compra Anulada') {
            return 'compra';
        }
        if (strpos($this->detalle , 'Traslado') !== false || strpos($this->detalle , 'traslado') !== false) {
            return 'traslado';
        }
        if (strpos($this->detalle , 'Ajuste') !== false || strpos($this->detalle , 'ajuste') !== false) {
            return 'ajuste';
        }
        if ($this->detalle == 'Actualización de producto' || $this->detalle == 'Actualización de producto desde Shopify') {
            return 'producto';
        }
        if ($this->detalle == 'Otra Entrada' || $this->detalle == 'Otra Entrada Anulada') {
            return 'entrada/detalle';
        }
        if ($this->detalle == 'Otra Salida' || $this->detalle == 'Otra Salida Anulada') {
            return 'salida/detalle';
        }
    }

    public function inventario(){
        return $this->belongsTo('App\Models\Inventario\Bodega','id_inventario');
    }

    public function producto(){
        return $this->belongsTo('App\Models\Inventario\Producto','id_producto')->withoutGlobalScopes();
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User','id_usuario');
    }

    /**
     * Obtiene el número de lote si el movimiento tiene un lote asociado
     */
    public function getNumeroLoteAttribute()
    {
        $loteId = $this->resolverLoteIdEfectivo();
        if ($loteId) {
            $lote = Lote::find($loteId);
            return $lote ? ($lote->numero_lote ?: 'Sin número') : null;
        }

        return null;
    }

    /**
     * Lote efectivo del movimiento (columna kardexs.lote_id o resolución por documento origen).
     */
    public function resolverLoteIdEfectivo(): ?int
    {
        if (!empty($this->attributes['lote_id'])) {
            return (int) $this->attributes['lote_id'];
        }

        if (stripos((string) $this->detalle, 'traslado') !== false) {
            return $this->resolverLoteIdTraslado();
        }

        // Si es un ajuste, obtener el lote desde el ajuste
        if (strpos($this->detalle, 'Ajuste') !== false || strpos($this->detalle, 'ajuste') !== false) {
            $ajuste = Ajuste::find($this->referencia);
            if ($ajuste && $ajuste->lote_id) {
                return (int) $ajuste->lote_id;
            }
        }
        
        // Si es una venta, obtener el lote desde kardex, detalle o detalle_venta_lotes
        if ($this->detalle == 'Venta' || $this->detalle == 'Venta a consigna' || $this->detalle == 'Venta Anulada') {
            $venta = \App\Models\Ventas\Venta::find($this->referencia);
            if ($venta) {
                $detalleVenta = \App\Models\Ventas\Detalle::where('id_venta', $venta->id)
                    ->where('id_producto', $this->id_producto)
                    ->first();
                if ($detalleVenta) {
                    if ($detalleVenta->lote_id) {
                        return (int) $detalleVenta->lote_id;
                    }
                    $cantidadMov = (float) ($this->salida_cantidad ?: $this->entrada_cantidad ?: 0);
                    if ($cantidadMov > 0) {
                        $asig = \App\Models\Ventas\DetalleVentaLote::where('id_detalle_venta', $detalleVenta->id)
                            ->whereRaw('ABS(cantidad - ?) < 0.0001', [$cantidadMov])
                            ->first();
                        if ($asig && $asig->lote_id) {
                            return (int) $asig->lote_id;
                        }
                    }
                }
            }
        }

        // Si es una compra, obtener el lote desde el detalle de compra
        if ($this->detalle == 'Compra' || $this->detalle == 'Compra a consigna' || $this->detalle == 'Compra Anulada') {
            $compra = \App\Models\Compras\Compra::find($this->referencia);
            if ($compra) {
                $detalleCompra = \App\Models\Compras\Detalle::where('id_compra', $compra->id)
                    ->where('id_producto', $this->id_producto)
                    ->whereNotNull('lote_id')
                    ->first();
                if ($detalleCompra && $detalleCompra->lote_id) {
                    return (int) $detalleCompra->lote_id;
                }
            }
        }

        return null;
    }

    /**
     * Resuelve el lote de un movimiento de traslado (incluye multilote vía traslado_lotes).
     */
    public function resolverLoteIdTraslado(): ?int
    {
        $cantidadMov = (float) ($this->salida_cantidad ?: $this->entrada_cantidad ?: 0);
        if ($cantidadMov <= 0 || !$this->referencia) {
            return null;
        }

        $esSalida = (float) ($this->salida_cantidad ?? 0) > 0;

        $traslado = Traslado::withoutGlobalScopes()
            ->with('loteAsignaciones')
            ->find($this->referencia);

        if (!$traslado) {
            return null;
        }

        if ($traslado->loteAsignaciones->isNotEmpty()) {
            $candidatos = [];
            foreach ($traslado->loteAsignaciones as $fila) {
                if (abs((float) $fila->cantidad - $cantidadMov) > 0.0001) {
                    continue;
                }
                $candidatos[] = $esSalida
                    ? (int) $fila->lote_id
                    : ($fila->lote_id_destino ? (int) $fila->lote_id_destino : (int) $fila->lote_id);
            }

            if (count($candidatos) === 1) {
                return $candidatos[0];
            }

            if (!empty($candidatos)) {
                $idBodegaMov = (int) $this->id_inventario;
                foreach ($candidatos as $loteId) {
                    $lote = Lote::find($loteId);
                    if ($lote && (int) $lote->id_bodega === $idBodegaMov) {
                        return $loteId;
                    }
                }
                return $candidatos[0];
            }
        }

        if ($traslado->lote_id) {
            if ($esSalida) {
                return (int) $traslado->lote_id;
            }
            return $traslado->lote_id_destino ? (int) $traslado->lote_id_destino : (int) $traslado->lote_id;
        }

        return null;
    }

    public function coincideConLote(int $loteId): bool
    {
        $loteIdMov = $this->resolverLoteIdEfectivo();
        return $loteIdMov !== null && $loteIdMov === $loteId;
    }

    /**
     * Nombre del proveedor (compras) o origen (ventas/cliente) para formato kardex farmacia
     */
    public function getNombreProveedorOrigenAttribute()
    {
        if ($this->detalle == 'Compra' || $this->detalle == 'Compra a consigna' || $this->detalle == 'Compra Anulada') {
            $compra = \App\Models\Compras\Compra::find($this->referencia);
            if ($compra && $compra->proveedor) {
                return $compra->proveedor->tipo == 'Empresa' ? ($compra->proveedor->nombre_empresa ?? '') : trim(($compra->proveedor->nombre ?? '') . ' ' . ($compra->proveedor->apellido ?? ''));
            }
        }
        if (str_contains($this->detalle, 'Devolución Compra')) {
            $devolucion = \App\Models\Compras\Devoluciones\Devolucion::find($this->referencia);
            if ($devolucion && $devolucion->proveedor) {
                return $devolucion->proveedor->tipo == 'Empresa' ? ($devolucion->proveedor->nombre_empresa ?? '') : trim(($devolucion->proveedor->nombre ?? '') . ' ' . ($devolucion->proveedor->apellido ?? ''));
            }
        }
        if ($this->detalle == 'Venta' || $this->detalle == 'Venta a consigna' || $this->detalle == 'Venta Anulada' || str_contains($this->detalle, 'Devolución Venta')) {
            $venta = \App\Models\Ventas\Venta::find($this->referencia);
            if ($venta && $venta->cliente) {
                return $venta->cliente->nombre ?? '';
            }
        }
        return '';
    }

    /**
     * Nacionalidad del proveedor (compras) para formato kardex farmacia
     */
    public function getNacionalidadProveedorAttribute()
    {
        if ($this->detalle == 'Compra' || $this->detalle == 'Compra a consigna' || $this->detalle == 'Compra Anulada') {
            $compra = \App\Models\Compras\Compra::find($this->referencia);
            if ($compra && $compra->proveedor && $compra->proveedor->pais) {
                return $compra->proveedor->pais;
            }
        }
        if (str_contains($this->detalle, 'Devolución Compra')) {
            $devolucion = \App\Models\Compras\Devoluciones\Devolucion::find($this->referencia);
            if ($devolucion && $devolucion->proveedor && $devolucion->proveedor->pais) {
                return $devolucion->proveedor->pais;
            }
        }
        return '';
    }

}



