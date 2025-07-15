<?php

namespace App\Imports;

use App\Models\Inventario\Producto;
use App\Models\Inventario\Categorias\Categoria;
use App\Models\Inventario\Bodega;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Ajuste;
use App\Models\Compras\Proveedores\Proveedor;
use App\Models\Inventario\Proveedor as ProductoProveedor;
use Illuminate\Support\Facades\Auth;

use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use JWTAuth;

class Productos implements ToModel, WithHeadingRow, WithValidation, WithBatchInserts, WithChunkReading
{
    // use Importable;

    private $numRows = 0;
    private $bodegas = null;
    private $stockColumns = [];
    
    public function __construct()
    {
        // Las bodegas se cargarán dinámicamente en el método model
        $this->bodegas = null;
    }

    public function model(array $row)
    {
        $usuario = JWTAuth::parseToken()->authenticate();

        // Cargar bodegas si no están cargadas
        if ($this->bodegas === null) {
            $this->bodegas = Bodega::where('id_empresa', $usuario->id_empresa)
                                   ->where('activo', true)
                                   ->orderBy('id')
                                   ->get();
        }

        $id_categoria = Categoria::where('nombre', $row['categoria'])
                                ->where('id_empresa', $usuario->id_empresa)
                                ->pluck('id')->first();
        

        if(!$id_categoria){
            $categoria = new Categoria();
            $categoria->nombre = $row['categoria'];
            $categoria->descripcion = $row['categoria'];
            $categoria->enable = true;
            $categoria->id_empresa = $usuario->id_empresa;
            $categoria->save();
            $id_categoria = $categoria->id;
        }

        if ($row['proveedor_nombre']) {
            $id_proveedor = Proveedor::where(function ($query) use ($row) {
                    $query->where('nombre', $row['proveedor_nombre'])
                          ->where('apellido', $row['proveedor_apellido']);
                })
                ->orWhere('nombre_empresa', $row['proveedor_nombre'])
                ->where('id_empresa', $usuario->id_empresa)
                ->pluck('id')
                ->first();

            if(!$id_proveedor){
                $proveedor = new Proveedor();
                $proveedor->nombre = $row['proveedor_apellido'] ? $row['proveedor_nombre'] : null;
                $proveedor->apellido = $row['proveedor_apellido'];
                $proveedor->tipo = $row['proveedor_apellido'] ? 'Persona' : 'Empresa';
                $proveedor->nombre_empresa = $proveedor->tipo == 'Empresa' ? $row['proveedor_nombre'] : NULL;
                $proveedor->enable = true;
                $proveedor->id_empresa = $usuario->id_empresa;
                $proveedor->id_usuario = $usuario->id;
                $proveedor->save();
                $id_proveedor = $proveedor->id;
            }
        }

        $producto = Producto::where('nombre', $row['nombre'])
                                ->when(isset($row['codigo']) && !empty($row['codigo']), function ($query) use ($row) {
                                    return $query->where('codigo', $row['codigo']);
                                })
                                ->where('id_empresa', $usuario->id_empresa)
                                ->first();

        if(!$producto){
            $producto = new Producto();
            ++$this->numRows;
        }

        // Calcular stock total sumando todas las bodegas
        $stockColumns = $this->detectarColumnasStock($row);
        $stockTotal = array_sum($stockColumns);

        $producto->nombre = $row['nombre'];
        $producto->precio = $row['precio'];
        $producto->costo = $row['costo'];
        $producto->costo_promedio = $row['costo'];
        $producto->stock = $stockTotal; // Stock total de todas las bodegas
        $producto->id_categoria = $id_categoria;
        $producto->codigo = $row['codigo'];
        $producto->descripcion = $row['descripcion'];
        $producto->marca = $row['marca'];
        $producto->medida = $row['unidad_medida'];
        $producto->barcode = $row['codigo_de_barra'];
        $producto->enable  = true;
        $producto->id_empresa =  $usuario->id_empresa;
        $producto->save();

        if (isset($id_proveedor)) {
            ProductoProveedor::create([
                'id_proveedor' => $id_proveedor,
                'id_producto' => $producto->id,
            ]);
        }

        // Procesar stock para cada bodega de forma dinámica
        $this->procesarStockPorBodegas($producto, $row, $usuario);

        return $producto;
    }

    /**
     * Procesa el stock para cada bodega de forma dinámica
     */
    private function procesarStockPorBodegas($producto, $row, $usuario)
    {
        // Detectar columnas de stock disponibles en el Excel
        $stockColumns = $this->detectarColumnasStock($row);
        
        foreach ($this->bodegas as $index => $bodega) {
            $columnName = 'sucursal_' . ($index + 1) . '_stock';
            $stockValue = isset($stockColumns[$columnName]) ? $stockColumns[$columnName] : 0;
            
            // Buscar o crear inventario para esta bodega
            $inventario = Inventario::where('id_producto', $producto->id)
                                   ->where('id_bodega', $bodega->id)
                                   ->first();

            if (!$inventario) {
                $inventario = new Inventario();
            }

            $inventario->id_producto = $producto->id;
            $inventario->id_bodega = $bodega->id;
            $inventario->stock = $stockValue;
            $inventario->save();

            // Crear ajuste inicial si hay stock
            if ($stockValue > 0) {
                $ajuste = Ajuste::create([
                    'concepto' => 'Ajuste inicial',
                    'id_producto' => $producto->id,
                    'id_bodega' => $bodega->id,
                    'stock_actual' => 0,
                    'stock_real' => $inventario->stock,
                    'ajuste' => $inventario->stock,
                    'estado' => 'Confirmado',
                    'id_empresa' => $usuario->id_empresa,
                    'id_usuario' => $usuario->id,
                ]);

                // Actualizar kardex si el método existe
                if (method_exists($inventario, 'kardex')) {
                    $inventario->kardex($ajuste, $ajuste->ajuste);
                }
            }
        }
    }

    /**
     * Detecta las columnas de stock disponibles en el Excel
     */
    private function detectarColumnasStock($row)
    {
        $stockColumns = [];
        
        // Buscar todas las columnas que sigan el patrón sucursal_X_stock
        foreach ($row as $columnName => $value) {
            if (preg_match('/^sucursal_(\d+)_stock$/', $columnName, $matches)) {
                $stockNumber = (int)$matches[1];
                $stockValue = is_numeric($value) ? (float)$value : 0;
                $stockColumns[$columnName] = $stockValue;
            }
        }
        
        return $stockColumns;
    }

    public function rules(): array
    {
        $rules = [
            'nombre' => 'required|string',
            'precio' => 'required|numeric',
            'costo' => 'required|numeric',
            'categoria' => 'required|string',
            'proveedor_apellido' => 'required_with:proveedor_nombre',
        ];

        // Agregar reglas dinámicas para las columnas de stock
        // Como las bodegas se cargan dinámicamente, usamos un patrón más flexible
        $rules['sucursal_*_stock'] = 'nullable|numeric|min:0';

        return $rules;
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function getRowCount(): int
    {
        return $this->numRows;
    }
}
