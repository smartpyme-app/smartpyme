<?php

namespace App\Imports;

use App\Models\Inventario\Inventario;
use App\Models\Inventario\Ajuste;
use App\Models\Inventario\Bodega;
use App\Models\Inventario\Producto;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
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

    /**
     * Fila en la que empiezan los encabezados
     */
    public function startRow(): int
    {
        return 1; // Primera fila (donde están los encabezados)
    }

    /**
     * @param array $row
     *
     * @return null
     */
    public function model(array $row)
    {
        // Registrar cada fila para depuración
        Log::info('Fila procesada:', $row);
        
        try {
            // Verificar que la fila tenga las claves esperadas
            if (!isset($row['id']) || !isset($row['stock_nuevo']) || !isset($row['bodega'])) {
                // Registrar las claves disponibles
                Log::warning('Claves faltantes. Claves disponibles:', array_keys($row));
                return null;
            }
            
            // Obtener los valores de la fila
            $idProducto = $row['id'];
            $stockNuevo = $row['stock_nuevo'];
            $nombreBodega = $row['bodega'];
            
            // Convertir valores si es necesario
            if (!is_numeric($stockNuevo)) {
                Log::warning("Stock nuevo no es numérico: {$stockNuevo}");
                return null;
            }
            
            // Buscar la bodega por nombre para obtener su ID
            $partesBodega = explode(' - ', $nombreBodega);
            $nombreSucursal = trim($partesBodega[0]);
            
            $bodega = Bodega::where('nombre', 'like', $nombreSucursal . '%')->first();
            
            if (!$bodega) {
                Log::warning("No se encontró la bodega: {$nombreBodega}");
                return null;
            }
            
            // Buscar el inventario
            $inventario = Inventario::where('id_producto', $idProducto)
                ->where('id_bodega', $bodega->id)
                ->first();
            
            if (!$inventario) {
                Log::warning("No se encontró inventario para producto {$idProducto} en bodega {$bodega->id}");
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
            $ajuste->id_bodega = $bodega->id;
            $ajuste->id_usuario = Auth::id();
            $ajuste->id_empresa = Auth::user()->id_empresa;
            $ajuste->save();
            
            // Actualizar el stock
            $inventario->stock = $stockNuevo;
            $inventario->save();
            
            // Registrar en el kardex
            $inventario->kardex($ajuste, $diferencia);
            
            // Incrementar contador
            $this->actualizados++;
            
            Log::info("Producto {$producto->nombre} actualizado. Diferencia: {$diferencia}");
        } catch (\Exception $e) {
            Log::error("Error procesando fila: " . $e->getMessage(), $row);
        }
        
        return null;
    }

    /**
     * @return array
     */
    // public function rules(): array
    // {
    //     return [
    //         'id' => 'required|exists:productos,id',
    //         'stock_nuevo' => 'required|numeric|min:0',
    //         'bodega' => 'required|string',
    //     ];
    // }

    /**
     * @return int
     */
    public function getActualizados(): int
    {
        return $this->actualizados;
    }
}