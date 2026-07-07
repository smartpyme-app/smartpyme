<?php

namespace App\Models\Inventario\Salidas;

use App\Models\Concerns\AuditableModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Lote;
use App\Models\Admin\Empresa;
use App\Models\Inventario\Salidas\DetalleSalidaLote;
use App\Services\Inventario\ConversionInventarioService;
use App\Services\Inventario\LoteAsignacionService;

class Salida extends AuditableModel {

    protected static function auditModule(): string
    {
        return 'inventario';
    }

    protected $table = 'inventario_salidas';
    protected $fillable = array(
        'fecha',
        'id_bodega',
        'concepto',
        'tipo',
        'estado',
        'id_usuario',
        'id_empresa',
    );

    protected $appends = ['usuario_nombre', 'bodega_nombre'];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            static::addGlobalScope('empresa', function (Builder $builder) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            });
            
            static::addGlobalScope('sucursal', function (Builder $builder) {
                // Solo aplicar el filtro si el usuario está autenticado y no es administrador
                if (Auth::check() && Auth::user()->tipo != 'Administrador') {
                    $builder->whereHas('bodega', function ($query) {
                        $query->where('id_sucursal', Auth::user()->id_sucursal);
                    });
                }
            });
        }
    }

    public function getUsuarioNombreAttribute(){
        return $this->usuario()->pluck('name')->first();
    }

    public function getBodegaNombreAttribute(){
        return $this->bodega()->pluck('nombre')->first();
    }

    public function detalles(){
        return $this->hasMany('App\Models\Inventario\Salidas\Detalle', 'id_salida');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User', 'id_usuario');
    }

    public function bodega(){
        return $this->belongsTo('App\Models\Inventario\Bodega', 'id_bodega');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Admin\Empresa', 'id_empresa');
    }

    /**
     * Actualiza el inventario para todos los detalles de la salida
     */
    public function actualizarInventario()
    {
        foreach ($this->detalles as $detalle) {
            $this->actualizarStockProducto($detalle);
        }
    }

    /**
     * Actualiza el stock de un producto específico
     */
    public function actualizarStockProducto($detalle)
    {
        $producto = Producto::findOrFail($detalle->id_producto);
        
        $empresa = Empresa::find($this->id_empresa);
        $lotesActivo = $empresa ? $empresa->isLotesActivo() : false;
        
        $factor = 1;
        if ($detalle->id_presentacion) {
            $presentacion = \App\Models\Inventario\ProductoPresentacion::find($detalle->id_presentacion);
            if ($presentacion) {
                $factor = $presentacion->factor_conversion ?: 1;
            }
        }
        
        $cantidadBase = ConversionInventarioService::calcularCantidadBase($detalle->cantidad, $factor);

        $registrosLotes = DetalleSalidaLote::where('id_detalle_salida', $detalle->id)->get();

        if ($producto->inventario_por_lotes && $lotesActivo && $registrosLotes->isNotEmpty()) {
            $asignaciones = [];
            foreach ($registrosLotes as $registro) {
                $lote = Lote::find($registro->lote_id);
                if (!$lote) {
                    throw new \Exception("No se encontró el lote asignado para el producto: {$producto->nombre}.");
                }
                if ($lote->id_producto != $detalle->id_producto || $lote->id_bodega != $this->id_bodega) {
                    throw new \Exception("El lote seleccionado no corresponde al producto o bodega especificados.");
                }
                if ($lote->stock < (float) $registro->cantidad) {
                    throw new \Exception("No hay suficiente stock en el lote para el producto: {$producto->nombre}. Stock disponible en lote: {$lote->stock}, Cantidad requerida: {$registro->cantidad}");
                }
                $asignaciones[] = [
                    'lote_id' => (int) $lote->id,
                    'cantidad' => (float) $registro->cantidad,
                    'lote' => $lote,
                ];
            }

            $inventario = Inventario::where('id_producto', $producto->id)
                ->where('id_bodega', $this->id_bodega)
                ->first();

            if (!$inventario) {
                throw new \Exception("No existe inventario para el producto: {$producto->nombre} en la bodega seleccionada.");
            }

            if ($inventario->stock < $cantidadBase) {
                throw new \Exception("No hay suficiente stock para el producto: {$producto->nombre}. Stock disponible: {$inventario->stock}, Cantidad requerida: {$cantidadBase}");
            }

            LoteAsignacionService::aplicarSalidaDocumento($asignaciones, $this, $inventario, (float) $detalle->costo);
            return;
        }

        if ($producto->inventario_por_lotes && $lotesActivo && $detalle->lote_id) {
            // Actualizar stock del lote
            $lote = Lote::find($detalle->lote_id);
            if ($lote) {
                // Verificar que el lote pertenezca al producto y bodega correctos
                if ($lote->id_producto != $detalle->id_producto || $lote->id_bodega != $this->id_bodega) {
                    throw new \Exception("El lote seleccionado no corresponde al producto o bodega especificados.");
                }
                
                // Validar que hay suficiente stock en el lote
                if ($lote->stock < $cantidadBase) {
                    throw new \Exception("No hay suficiente stock en el lote para el producto: {$producto->nombre}. Stock disponible en lote: {$lote->stock}, Cantidad requerida: {$cantidadBase}");
                }
                
                // Disminuir stock del lote
                $lote->stock -= $cantidadBase;
                $lote->save();
            }
        }
        
        // Buscar el inventario para este producto en esta bodega
        $inventario = Inventario::where('id_producto', $producto->id)
                                ->where('id_bodega', $this->id_bodega)
                                ->first();

        if ($inventario) {
            // Validar que hay suficiente stock
            if ($inventario->stock < $cantidadBase) {
                throw new \Exception("No hay suficiente stock para el producto: {$producto->nombre}. Stock disponible: {$inventario->stock}, Cantidad requerida: {$cantidadBase}");
            }

            // Disminuir stock (siempre actualizar inventario tradicional para consistencia)
            $inventario->stock -= $cantidadBase;
            $inventario->save();

            // Registrar en kardex (con lote_id si aplica, igual que ventas)
            $kardexOpts = ($producto->inventario_por_lotes && $lotesActivo && $detalle->lote_id)
                ? ['lote_id' => $detalle->lote_id]
                : [];
            $inventario->kardex($this, $cantidadBase * -1, null, $detalle->costo, null, $kardexOpts);
        }
    }

    /**
     * Revierte el inventario (para anulaciones)
     */
    public function revertirInventario()
    {
        foreach ($this->detalles as $detalle) {
            $this->revertirStockProducto($detalle);
        }
    }

    /**
     * Revierte el stock de un producto específico
     */
    public function revertirStockProducto($detalle)
    {
        $producto = Producto::findOrFail($detalle->id_producto);
        
        $empresa = Empresa::find($this->id_empresa);
        $lotesActivo = $empresa ? $empresa->isLotesActivo() : false;
        
        $factor = 1;
        if ($detalle->id_presentacion) {
            $presentacion = \App\Models\Inventario\ProductoPresentacion::find($detalle->id_presentacion);
            if ($presentacion) {
                $factor = $presentacion->factor_conversion ?: 1;
            }
        }
        
        $cantidadBase = ConversionInventarioService::calcularCantidadBase($detalle->cantidad, $factor);
        
        $registrosLotes = DetalleSalidaLote::where('id_detalle_salida', $detalle->id)->get();

        $inventario = Inventario::where('id_producto', $producto->id)
            ->where('id_bodega', $this->id_bodega)
            ->first();

        if ($producto->inventario_por_lotes && $lotesActivo && ($registrosLotes->isNotEmpty() || $detalle->lote_id)) {
            if (!$inventario) {
                return;
            }

            LoteAsignacionService::revertirSalidaDocumento(
                $registrosLotes,
                $this,
                $inventario,
                $cantidadBase,
                $detalle->lote_id,
                (float) $detalle->costo
            );
            return;
        }

        if ($producto->inventario_por_lotes && $lotesActivo && $detalle->lote_id) {
            // Revertir stock del lote
            $lote = Lote::find($detalle->lote_id);
            if ($lote) {
                // Aumentar stock del lote (revertir la salida)
                $lote->stock += $cantidadBase;
                $lote->save();
            }
        }
        
        $inventario = Inventario::where('id_producto', $producto->id)
                                ->where('id_bodega', $this->id_bodega)
                                ->first();

        if ($inventario) {
            // Aumentar stock (revertir la salida)
            $inventario->stock += $cantidadBase;
            $inventario->save();

            // Registrar en kardex
            $kardexOpts = ($producto->inventario_por_lotes && $lotesActivo && $detalle->lote_id)
                ? ['lote_id' => $detalle->lote_id]
                : [];
            $inventario->kardex($this, $cantidadBase, null, $detalle->costo, null, $kardexOpts);
        }
    }
    public function aprobar()
    {
        $this->estado = 'Aprobada';
        $this->save();
        $this->actualizarInventario();
    }

    /**
     * Anular la salida
     */
    public function anular()
    {
        $wasApproved = $this->estado === 'Aprobada';
        $this->estado = 'Anulada';
        $this->save();

        if ($wasApproved) {
            $this->revertirInventario();
        }
    }

}



