<?php

namespace App\Imports;

use App\Models\Inventario\Inventario;
use App\Models\Inventario\Ajuste;
use App\Models\Inventario\Bodega;
use App\Models\Inventario\Producto;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\Importable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InventarioImport implements ToModel, WithHeadingRow, WithStartRow
{
    use Importable;

    protected $detalleAjuste;
    protected $actualizados = 0;

    public function __construct($detalleAjuste)
    {
        $this->detalleAjuste = $detalleAjuste;
    }

 
    public function startRow(): int
    {
        return 1; 
    }

    /**
     * @param array $row
     *
     * @return null
     */
    public function model(array $row)
    {
      
        Log::info('Fila procesada:', $row);
        
        try {
          
            if (!isset($row['id']) || !isset($row['id_bodega']) || !isset($row['stock_nuevo'])) {
                // Registrar las claves disponibles
                Log::warning('Claves faltantes. Claves disponibles:', array_keys($row));
                return null;
            }
            
   
            $idProducto = $row['id'];
            $idBodega = $row['id_bodega'];
            $stockNuevo = $row['stock_nuevo'];
            
            // Convertir valores si es necesario
            if (!is_numeric($stockNuevo)) {
                Log::warning("Stock nuevo no es numérico: {$stockNuevo}");
                return null;
            }
            
            // Buscar el inventario directamente por ID de producto e ID de bodega
            $inventario = Inventario::where('id_producto', $idProducto)
                ->where('id_bodega', $idBodega)
                ->first();
            
            if (!$inventario) {
                Log::warning("No se encontró inventario para producto {$idProducto} en bodega {$idBodega}");
                return null;
            }
            
            // Buscar el producto para incluir su nombre en el ajuste
            $producto = Producto::find($idProducto);
            if (!$producto) {
                Log::warning("No se encontró el producto con ID: {$idProducto}");
                return null;
            }
            
            // Calcular la diferencia para el kardex
            $stockActual = $inventario->stock;
            $diferencia = $stockNuevo - $stockActual;
            
            // Si no hay cambio, saltar
            if ($diferencia == 0) {
                Log::info("Sin cambio para producto {$idProducto}. Stock actual = {$stockActual}");
                return null;
            }
            
            // Crear un ajuste individual para este producto
            $ajuste = new Ajuste();
            $ajuste->concepto = $this->detalleAjuste . " - " . $producto->nombre;
            $ajuste->estado = 'Procesado';
            $ajuste->id_producto = $idProducto;
            $ajuste->id_bodega = $idBodega;
            $ajuste->id_usuario = Auth::id();
            $ajuste->stock_actual = $stockActual;
            $ajuste->stock_real = $stockNuevo;
            $ajuste->ajuste = $diferencia;
            $ajuste->id_empresa = Auth::user()->id_empresa;
            $ajuste->save();
            
            // Actualizar el stock
            $inventario->stock = $stockNuevo;
            $inventario->save();
            
            $inventario->kardex($ajuste, $diferencia);
            
            $this->actualizados++;
            
            Log::info("Producto {$producto->nombre} actualizado. Diferencia: {$diferencia}");
        } catch (\Exception $e) {
            Log::error("Error procesando fila: " . $e->getMessage(), $row);
        }
        
        return null;
    }

    /**
     * @return int
     */
    public function getActualizados(): int
    {
        return $this->actualizados;
    }
}