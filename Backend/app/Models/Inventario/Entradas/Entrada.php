<?php

namespace App\Models\Inventario\Entradas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Auth;
use App\Models\Inventario\Producto;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Lote;
use App\Models\Admin\Empresa;

class Entrada extends Model {

    protected $table = 'inventario_entradas';
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
        return $this->hasMany('App\Models\Inventario\Entradas\Detalle', 'id_entrada');
    }

    public function usuario(){
        return $this->belongsTo('App\Models\User', 'id_usuario');
    }

    public function bodega(){
        return $this->belongsTo('App\Models\Inventario\Bodega', 'id_bodega');
    }

    public function empresa(){
        return $this->belongsTo('App\Models\Empresa', 'id_empresa');
    }

    /**
     * Actualiza el inventario para todos los detalles de la entrada
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
        
        // Verificar si la empresa tiene lotes activos
        $empresa = Empresa::find($this->id_empresa);
        $lotesActivo = false;
        if ($empresa && $empresa->custom_empresa) {
            $customConfig = is_string($empresa->custom_empresa) 
                ? json_decode($empresa->custom_empresa, true) 
                : $empresa->custom_empresa;
            $lotesActivo = $customConfig['configuraciones']['lotes_activo'] ?? false;
        }
        
        // Si el producto tiene inventario por lotes y lotes está activo
        if ($producto->inventario_por_lotes && $lotesActivo && $detalle->lote_id) {
            // Actualizar stock del lote
            $lote = Lote::find($detalle->lote_id);
            if ($lote) {
                // Verificar que el lote pertenezca al producto y bodega correctos
                if ($lote->id_producto != $detalle->id_producto || $lote->id_bodega != $this->id_bodega) {
                    throw new \Exception("El lote seleccionado no corresponde al producto o bodega especificados.");
                }
                
                // Sumar stock al lote
                $lote->stock += $detalle->cantidad;
                $lote->save();
            }
        }
        
        // Buscar el inventario para este producto en esta bodega
        $inventario = Inventario::where('id_producto', $producto->id)
                                ->where('id_bodega', $this->id_bodega)
                                ->first();

        if ($inventario) {
            // Sumar stock (siempre actualizar inventario tradicional para consistencia)
            $inventario->stock += $detalle->cantidad;
            $inventario->save();

            // Registrar en kardex
            $inventario->kardex($this, $detalle->cantidad, null, $detalle->costo);
        }
    }

    /**
     * Aprobar la entrada
     */
    public function aprobar()
    {
        $this->estado = 'Aprobada';
        $this->save();
        $this->actualizarInventario();
    }

    /**
     * Anular la entrada
     */
    public function anular()
    {
        $this->estado = 'Anulada';
        $this->save();
        $this->revertirInventario();
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
        
        // Verificar si la empresa tiene lotes activos
        $empresa = Empresa::find($this->id_empresa);
        $lotesActivo = false;
        if ($empresa && $empresa->custom_empresa) {
            $customConfig = is_string($empresa->custom_empresa) 
                ? json_decode($empresa->custom_empresa, true) 
                : $empresa->custom_empresa;
            $lotesActivo = $customConfig['configuraciones']['lotes_activo'] ?? false;
        }
        
        // Si el producto tiene inventario por lotes y lotes está activo
        if ($producto->inventario_por_lotes && $lotesActivo && $detalle->lote_id) {
            // Revertir stock del lote
            $lote = Lote::find($detalle->lote_id);
            if ($lote) {
                $lote->stock -= $detalle->cantidad;
                
                // Validar que no quede stock negativo
                if ($lote->stock < 0) {
                    throw new \Exception("No hay suficiente stock en el lote para revertir la entrada. Producto: {$producto->nombre}");
                }
                
                $lote->save();
            }
        }
        
        $inventario = Inventario::where('id_producto', $producto->id)
                                ->where('id_bodega', $this->id_bodega)
                                ->first();

        if ($inventario) {
            $inventario->stock -= $detalle->cantidad;
            
            // Validar que no quede stock negativo
            if ($inventario->stock < 0) {
                throw new \Exception("No hay suficiente stock para revertir la entrada. Producto: {$producto->nombre}");
            }
            
            $inventario->save();
            $inventario->kardex($this, $detalle->cantidad * -1, null, $detalle->costo);
        }
    }

}



