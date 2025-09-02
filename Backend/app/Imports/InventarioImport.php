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
    protected $idBodegaSeleccionada;
    protected $actualizados = 0;
    protected $procesados = 0;
    protected $sinCambios = 0;
    protected $errores = 0;
    protected $sinInventario = 0;

    public function __construct($detalleAjuste, $idBodegaSeleccionada)
    {
        $this->detalleAjuste = $detalleAjuste;
        $this->idBodegaSeleccionada = $idBodegaSeleccionada;
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
        $this->procesados++;
        
        Log::info("Procesando fila #{$this->procesados}:", $row);
        
        try {
            // Log para debug - ver qué claves están disponibles
            Log::info("Fila #{$this->procesados} - Claves disponibles:", array_keys($row));
            
            // Intentar diferentes variaciones de nombres de columnas para ID y Stock
            $idProducto = null;
            $stockNuevo = null;
            
            // Buscar ID del producto
            if (isset($row['_id'])) {
                $idProducto = $row['_id'];
            } elseif (isset($row['id'])) {
                $idProducto = $row['id'];
            } elseif (isset($row['#id'])) {
                $idProducto = $row['#id'];
            }
            
            // Buscar stock nuevo
            if (isset($row['stock_nuevo'])) {
                $stockNuevo = $row['stock_nuevo'];
            } elseif (isset($row['stock nuevo'])) {
                $stockNuevo = $row['stock nuevo'];
            } elseif (isset($row['Stock Nuevo'])) {
                $stockNuevo = $row['Stock Nuevo'];
            }
            
            // Validar que encontramos los campos requeridos
            if (empty($idProducto) || $stockNuevo === null) {
                $this->errores++;
                Log::warning("Fila #{$this->procesados} - Campos requeridos faltantes. ID: {$idProducto}, Stock: {$stockNuevo}. Claves disponibles:", array_keys($row));
                return null;
            }
            
            // Validar que el ID del producto sea válido
            if (!is_numeric($idProducto)) {
                $this->errores++;
                Log::warning("Fila #{$this->procesados} - ID de producto inválido: {$idProducto}");
                return null;
            }
            
            // Validar que el stock nuevo sea numérico
            if (!is_numeric($stockNuevo)) {
                $this->errores++;
                Log::warning("Fila #{$this->procesados} - Stock nuevo no es numérico: {$stockNuevo} para producto ID: {$idProducto}");
                return null;
            }
            
            $idBodega = $this->idBodegaSeleccionada;
            
            // Obtener nombre del producto desde Excel para logging (opcional)
            $nombreEnExcel = $row['producto'] ?? $row['Producto'] ?? 'N/A';
            
            // Log detallado de los valores leídos
            Log::info("Fila #{$this->procesados} - Valores leídos: ID={$idProducto}, Stock Nuevo={$stockNuevo}, Nombre en Excel='{$nombreEnExcel}'");
            
            // Buscar el producto por ID
            $producto = Producto::find($idProducto);
            if (!$producto) {
                $this->errores++;
                Log::warning("Fila #{$this->procesados} - No se encontró el producto con ID: {$idProducto}. Nombre en Excel: '{$nombreEnExcel}'");
                return null;
            }
            
            // Log de éxito y verificación de coincidencia de nombres (para debug)
            Log::info("Fila #{$this->procesados} - Producto encontrado: '{$producto->nombre}' (ID: {$producto->id})");
            if ($nombreEnExcel !== 'N/A' && $producto->nombre !== $nombreEnExcel) {
                Log::info("Fila #{$this->procesados} - Nota: Nombre en Excel '{$nombreEnExcel}' vs BD '{$producto->nombre}' - usando BD");
            }
            
            // Buscar el inventario
            $inventario = Inventario::where('id_producto', $idProducto)
                ->where('id_bodega', $idBodega)
                ->first();
            
            if (!$inventario) {
                $this->sinInventario++;
                Log::warning("Fila #{$this->procesados} - No se encontró inventario para producto '{$producto->nombre}' (ID: {$idProducto}) en bodega {$idBodega}");
                return null;
            }
            
            // Calcular la diferencia
            $stockActual = $inventario->stock;
            $diferencia = $stockNuevo - $stockActual;
            
            // Si no hay cambio, saltar
            if ($diferencia == 0) {
                $this->sinCambios++;
                Log::info("Fila #{$this->procesados} - Sin cambio para producto '{$producto->nombre}'. Stock actual = {$stockActual}, stock nuevo = {$stockNuevo}");
                return null;
            }
            
            // Crear un ajuste individual para este producto
            $ajuste = new Ajuste();
            $ajuste->concepto = $this->detalleAjuste . " - " . $producto->nombre;
            $ajuste->estado = 'Confirmado';
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
            
            Log::info("Fila #{$this->procesados} - Producto '{$producto->nombre}' actualizado exitosamente. Diferencia: {$diferencia}");
            
        } catch (\Exception $e) {
            $this->errores++;
            Log::error("Fila #{$this->procesados} - Error procesando fila: " . $e->getMessage(), $row);
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

    /**
     * @return array
     */
    public function getEstadisticas(): array
    {
        return [
            'procesados' => $this->procesados,
            'actualizados' => $this->actualizados,
            'sin_cambios' => $this->sinCambios,
            'sin_inventario' => $this->sinInventario,
            'errores' => $this->errores
        ];
    }

    /**
     * Log final con estadísticas completas
     */
    public function logEstadisticasFinales()
    {
        Log::info("=== RESUMEN DE IMPORTACIÓN (BÚSQUEDA POR ID) ===");
        Log::info("Total de filas procesadas: {$this->procesados}");
        Log::info("Productos actualizados: {$this->actualizados}");
        Log::info("Productos sin cambios: {$this->sinCambios}");
        Log::info("Productos sin inventario en bodega: {$this->sinInventario}");
        Log::info("Errores encontrados: {$this->errores}");
        
        $noActualizados = $this->procesados - $this->actualizados;
        Log::info("Total de filas NO actualizadas: {$noActualizados}");
        
        if ($this->sinInventario > 0) {
            Log::warning("ATENCIÓN: {$this->sinInventario} productos no tienen inventario en la bodega seleccionada.");
        }
        if ($this->errores > 0) {
            Log::warning("ATENCIÓN: {$this->errores} filas tuvieron errores. Revisa los logs anteriores para más detalles.");
        }
        
        Log::info("=== FIN RESUMEN ===");
    }
}