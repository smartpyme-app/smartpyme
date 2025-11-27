<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Detalle;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\Admin\Empresa;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class InventarioService
{
    /**
     * Actualizar inventario al crear venta
     *
     * @param Venta $venta
     * @param array $detalles
     * @param bool $esCotizacion
     * @return void
     * @throws \Exception
     */
    public function actualizarInventarioVenta(Venta $venta, array $detalles, bool $esCotizacion = false): void
    {
        if ($esCotizacion) {
            return;
        }

        // Obtener la empresa para verificar configuración de vender sin stock
        $empresa = Empresa::findOrFail(Auth::user()->id_empresa);
        $puedeVenderSinStock = $empresa->vender_sin_stock == 1;

        foreach ($detalles as $det) {
            $this->validarYActualizarStockProducto($venta, $det, $puedeVenderSinStock);
            $this->validarYActualizarStockComposiciones($venta, $det, $puedeVenderSinStock);
        }
    }

    /**
     * Validar y actualizar stock de un producto
     *
     * @param Venta $venta
     * @param array $detalle
     * @param bool $puedeVenderSinStock
     * @return void
     * @throws \Exception
     */
    protected function validarYActualizarStockProducto(Venta $venta, array $detalle, bool $puedeVenderSinStock): void
    {
        // Obtener el producto para verificar si es servicio
        $producto = Producto::where('id', $detalle['id_producto'])->first();
        
        // Validar stock solo si no es servicio y si la empresa no permite vender sin stock
        if ($producto && $producto->tipo != 'Servicio') {
            $inventario = Inventario::where('id_producto', $detalle['id_producto'])
                ->where('id_bodega', $venta->id_bodega)
                ->first();
            
            // Validar stock disponible
            if ($inventario) {
                $stockDisponible = $inventario->stock;
                $cantidadRequerida = $detalle['cantidad'];
                
                // Si no se permite vender sin stock y no hay suficiente stock
                if (!$puedeVenderSinStock && $stockDisponible < $cantidadRequerida) {
                    throw new \Exception("No hay suficiente stock para el producto: {$producto->nombre}. Stock disponible: {$stockDisponible}, Cantidad requerida: {$cantidadRequerida}");
                }
            } else {
                // Si no existe inventario y no se permite vender sin stock
                if (!$puedeVenderSinStock) {
                    throw new \Exception("No existe inventario para el producto: {$producto->nombre} en la bodega seleccionada");
                }
            }
        }

        // Actualizar stock del producto principal
        $this->actualizarStockProducto($venta, $detalle);
    }

    /**
     * Validar y actualizar stock de composiciones
     *
     * @param Venta $venta
     * @param array $detalle
     * @param bool $puedeVenderSinStock
     * @return void
     * @throws \Exception
     */
    protected function validarYActualizarStockComposiciones(Venta $venta, array $detalle, bool $puedeVenderSinStock): void
    {
        if (!isset($detalle['composiciones'])) {
            return;
        }

        foreach ($detalle['composiciones'] as $comp) {
            $productoCompuesto = Producto::where('id', $comp['id_compuesto'])->first();
            
            // Validar stock de productos compuestos solo si no es servicio
            if ($productoCompuesto && $productoCompuesto->tipo != 'Servicio') {
                $inventarioComp = Inventario::where('id_producto', $comp['id_compuesto'])
                    ->where('id_bodega', $venta->id_bodega)
                    ->first();
                
                $cantidadCompRequerida = $detalle['cantidad'] * $comp['cantidad'];
                
                if ($inventarioComp) {
                    $stockDisponibleComp = $inventarioComp->stock;
                    
                    // Si no se permite vender sin stock y no hay suficiente stock
                    if (!$puedeVenderSinStock && $stockDisponibleComp < $cantidadCompRequerida) {
                        throw new \Exception("No hay suficiente stock para el producto compuesto: {$productoCompuesto->nombre}. Stock disponible: {$stockDisponibleComp}, Cantidad requerida: {$cantidadCompRequerida}");
                    }
                } else {
                    // Si no existe inventario y no se permite vender sin stock
                    if (!$puedeVenderSinStock) {
                        throw new \Exception("No existe inventario para el producto compuesto: {$productoCompuesto->nombre} en la bodega seleccionada");
                    }
                }
            }
            
            // Actualizar stock del producto compuesto
            $this->actualizarStockComposicion($venta, $detalle, $comp);
        }
    }

    /**
     * Actualizar stock de un producto
     *
     * @param Venta $venta
     * @param array $detalle
     * @return void
     */
    protected function actualizarStockProducto(Venta $venta, array $detalle): void
    {
        $inventario = Inventario::where('id_producto', $detalle['id_producto'])
            ->where('id_bodega', $venta->id_bodega)
            ->first();

        if ($inventario) {
            $inventario->stock -= $detalle['cantidad'];
            $inventario->save();
            $inventario->kardex($venta, $detalle['cantidad'], $detalle['precio']);
        }
    }

    /**
     * Actualizar stock de una composición específica
     *
     * @param Venta $venta
     * @param array $detalle
     * @param array $comp
     * @return void
     */
    protected function actualizarStockComposicion(Venta $venta, array $detalle, array $comp): void
    {
        $inventario = Inventario::where('id_producto', $comp['id_compuesto'])
            ->where('id_bodega', $venta->id_bodega)
            ->first();

        if ($inventario) {
            $cantidadTotal = $detalle['cantidad'] * $comp['cantidad'];
            $inventario->stock -= $cantidadTotal;
            $inventario->save();
            $inventario->kardex($venta, $cantidadTotal);
        }
    }

    /**
     * Revertir inventario al anular venta
     *
     * @param Venta $venta
     * @return void
     */
    public function revertirInventarioAnulacion(Venta $venta): void
    {
        foreach ($venta->detalles as $detalle) {
            $this->revertirStockProducto($venta, $detalle);
            $this->revertirStockComposiciones($venta, $detalle);
        }
    }

    /**
     * Revertir stock de un producto
     *
     * @param Venta $venta
     * @param Detalle $detalle
     * @return void
     */
    protected function revertirStockProducto(Venta $venta, Detalle $detalle): void
    {
        $inventario = Inventario::where('id_producto', $detalle->id_producto)
            ->where('id_bodega', $venta->id_bodega)
            ->first();

        if ($inventario) {
            $inventario->stock += $detalle->cantidad;
            $inventario->save();
            $inventario->kardex($venta, $detalle->cantidad * -1);
        }
    }

    /**
     * Revertir stock de composiciones
     *
     * @param Venta $venta
     * @param Detalle $detalle
     * @return void
     */
    protected function revertirStockComposiciones(Venta $venta, Detalle $detalle): void
    {
        foreach ($detalle->composiciones()->get() as $comp) {
            $inventario = Inventario::where('id_producto', $comp->id_producto)
                ->where('id_bodega', $venta->id_bodega)
                ->first();

            if ($inventario) {
                $cantidadTotal = $detalle->cantidad * $comp->cantidad;
                $inventario->stock += $cantidadTotal;
                $inventario->save();
                $inventario->kardex($venta, $cantidadTotal * -1);
            }
        }
    }

    /**
     * Aplicar inventario al cancelar anulación
     *
     * @param Venta $venta
     * @return void
     */
    public function aplicarInventarioCancelacionAnulacion(Venta $venta): void
    {
        foreach ($venta->detalles as $detalle) {
            $this->aplicarStockProducto($venta, $detalle);
            $this->aplicarStockComposiciones($venta, $detalle);
        }
    }

    /**
     * Aplicar stock de un producto
     *
     * @param Venta $venta
     * @param Detalle $detalle
     * @return void
     */
    protected function aplicarStockProducto(Venta $venta, Detalle $detalle): void
    {
        $inventario = Inventario::where('id_producto', $detalle->id_producto)
            ->where('id_bodega', $venta->id_bodega)
            ->first();

        if ($inventario) {
            $inventario->stock -= $detalle->cantidad;
            $inventario->save();
            $inventario->kardex($venta, $detalle->cantidad);
        }
    }

    /**
     * Aplicar stock de composiciones
     *
     * @param Venta $venta
     * @param Detalle $detalle
     * @return void
     */
    protected function aplicarStockComposiciones(Venta $venta, Detalle $detalle): void
    {
        foreach ($detalle->composiciones()->get() as $comp) {
            $inventario = Inventario::where('id_producto', $comp->id_producto)
                ->where('id_bodega', $venta->id_bodega)
                ->first();

            if ($inventario) {
                $cantidadTotal = $detalle->cantidad * $comp->cantidad;
                $inventario->stock -= $cantidadTotal;
                $inventario->save();
                $inventario->kardex($venta, $cantidadTotal);
            }
        }
    }
}


