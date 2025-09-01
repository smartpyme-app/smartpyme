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
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use JWTAuth;

class Productos implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    private $numRows = 0;
    private $usuario;
    private $bodegas;

    public function __construct()
    {
        $this->usuario = JWTAuth::parseToken()->authenticate();

        $this->bodegas = Bodega::where('id_empresa', $this->usuario->id_empresa)
            ->where('activo', true)
            ->get();
    }

    public function model(array $row)
    {
        if (empty($row['nombre']) || empty($row['precio']) || empty($row['costo']) || empty($row['categoria'])) {
            return null;
        }

        $id_categoria = Categoria::where('nombre', $row['categoria'])
            ->where('id_empresa', $this->usuario->id_empresa)
            ->pluck('id')->first();


        if (!$id_categoria) {
            $categoria = new Categoria();
            $categoria->nombre = $row['categoria'];
            $categoria->descripcion = $row['categoria'];
            $categoria->enable = true;
            $categoria->id_empresa = $this->usuario->id_empresa;
            $categoria->save();
            $id_categoria = $categoria->id;
        }

        Log::info($row['subcategoria']);
        $id_subcategoria = Categoria::where('nombre', $row['subcategoria'])
            ->where('id_empresa', $this->usuario->id_empresa)
            ->pluck('id')->first();


        if(!$id_subcategoria){
            $subcategoria = new Categoria();
            $subcategoria->nombre = $row['categoria'];
            $subcategoria->descripcion = $row['categoria'];
            $subcategoria->enable = true;
            $subcategoria->id_empresa = $this->usuario->id_empresa;
            $subcategoria->subcategoria = true;
            $subcategoria->id_cate_padre = $id_categoria;
            $subcategoria->save();
            $id_subcategoria = $subcategoria->id;
        }

        if ($row['proveedor_nombre']) {
            $id_proveedor = Proveedor::where(function ($query) use ($row) {
                $query->where('nombre', $row['proveedor_nombre'])
                    ->where('apellido', $row['proveedor_apellido']);
            })
                ->orWhere('nombre_empresa', $row['proveedor_nombre'])
                ->where('id_empresa', $this->usuario->id_empresa)
                ->pluck('id')
                ->first();

            if (!$id_proveedor) {
                $proveedor = new Proveedor();
                $proveedor->nombre = $row['proveedor_apellido'] ? $row['proveedor_nombre'] : null;
                $proveedor->apellido = $row['proveedor_apellido'];
                $proveedor->tipo = $row['proveedor_apellido'] ? 'Persona' : 'Empresa';
                $proveedor->nombre_empresa = $proveedor->tipo == 'Empresa' ? $row['proveedor_nombre'] : NULL;
                $proveedor->enable = true;
                $proveedor->id_empresa = $this->usuario->id_empresa;
                $proveedor->id_usuario = $this->usuario->id;
                $proveedor->save();
                $id_proveedor = $proveedor->id;
            }
        }

        $producto = Producto::where('nombre', $row['nombre'])
            ->when(isset($row['codigo']) && !empty($row['codigo']), function ($query) use ($row) {
                return $query->where('codigo', $row['codigo']);
            })
            ->where('id_empresa', $this->usuario->id_empresa)
            ->first();

        if (!$producto) {
            $producto = new Producto();
            ++$this->numRows;
        }

        $producto->nombre = $row['nombre'];
        $producto->precio = $row['precio'];
        $producto->costo = $row['costo'];
        $producto->costo_promedio = $row['costo'];
        $producto->stock = $row['sucursal_1_stock'];
        $producto->id_categoria = $id_categoria;
        $producto->id_subcategoria = $id_subcategoria ?? null;
        $producto->codigo = $row['codigo'];
        $producto->descripcion = $row['descripcion'];
        $producto->marca = $row['marca'];
        $producto->medida = $row['unidad_medida'];
        $producto->barcode = $row['codigo_de_barra'];
        $producto->enable  = true;
        $producto->id_empresa =  $this->usuario->id_empresa;
        $producto->save();

        if (isset($id_proveedor)) {
            ProductoProveedor::create([
                'id_proveedor' => $id_proveedor,
                'id_producto' => $producto->id,
            ]);
        }

        $bodegas = $this->bodegas;

        $inventariosExistentes = Inventario::where('id_producto', $producto->id)
            ->whereIn('id_bodega', $bodegas->pluck('id'))
            ->get()
            ->keyBy('id_bodega');

        if (isset($bodegas[0]) && isset($row['sucursal_1_stock'])) {
            $this->procesarInventarioBodega(
                $inventariosExistentes->get($bodegas[0]->id),
                $bodegas[0],
                $row['sucursal_1_stock'],
                $producto->id
            );
        }

        if (isset($bodegas[1]) && isset($row['sucursal_2_stock'])) {
            $this->procesarInventarioBodega(
                $inventariosExistentes->get($bodegas[1]->id),
                $bodegas[1],
                $row['sucursal_2_stock'],
                $producto->id
            );
        }

        if (isset($bodegas[2]) && isset($row['sucursal_3_stock'])) {
            $this->procesarInventarioBodega(
                $inventariosExistentes->get($bodegas[2]->id),
                $bodegas[2],
                $row['sucursal_3_stock'],
                $producto->id
            );
        }

        // ✅ Procesar bodegas adicionales (desde la 4ta en adelante)
        if ($bodegas->count() > 3) {
            for ($i = 3; $i < $bodegas->count(); $i++) {
                $this->procesarInventarioBodega(
                    $inventariosExistentes->get($bodegas[$i]->id),
                    $bodegas[$i],
                    0, // Stock inicial en 0 para bodegas adicionales
                    $producto->id
                );
            }
        }

        return $producto;
    }

    private function procesarInventarioBodega($inventarioExistente, $bodega, $stock, $productoId)
    {
        // Usar inventario existente o crear nuevo
        if (!$inventarioExistente) {
            $inventario = new Inventario();
            $inventario->id_producto = $productoId;
            $inventario->id_bodega = $bodega->id;
        } else {
            $inventario = $inventarioExistente;
        }

        $inventario->stock = $stock;
        $inventario->save();

        // Crear ajuste
        $ajuste = Ajuste::create([
            'concepto' => 'Ajuste inicial',
            'id_producto' => $productoId,
            'id_bodega' => $bodega->id,
            'stock_actual' => 0,
            'stock_real' => $inventario->stock,
            'ajuste' => $inventario->stock,
            'estado' => 'Confirmado',
            'id_empresa' => $this->usuario->id_empresa,
            'id_usuario' => $this->usuario->id,
        ]);

        if ($inventario->exists) {
            $inventario->kardex($ajuste, $ajuste->ajuste);
        }
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string',
            'precio' => 'required|numeric',
            'costo' => 'required|numeric',
            'sucursal_*_stock' => 'nullable|numeric|min:0',
            'categoria' => 'required|string',
            'proveedor_apellido' => 'required_with:proveedor_nombre',
        ];
    }


    public function getRowCount(): int
    {
        return $this->numRows;
    }
}
