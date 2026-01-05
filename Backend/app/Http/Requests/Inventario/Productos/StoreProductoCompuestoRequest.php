<?php

namespace App\Http\Requests\Inventario\Productos;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductoCompuestoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'id' => 'sometimes|nullable|integer|exists:productos,id',
            'nombre' => 'required|string|max:255',
            'precio' => 'required|numeric|min:0',
            'costo' => 'required|numeric|min:0',
            'id_categoria' => 'required|integer|exists:categorias,id',
            'id_empresa' => 'required|integer|exists:empresas,id',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_producto' => 'required|integer|exists:productos,id',
            'detalles.*.cantidad' => 'required|numeric|min:0.01',
            'id_bodega' => 'required|integer|exists:sucursal_bodegas,id',
            'stock' => 'sometimes|nullable|numeric|min:0',
            'codigo' => 'sometimes|nullable|string|max:255',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'El producto no existe.',
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'precio.required' => 'El precio es obligatorio.',
            'precio.numeric' => 'El precio debe ser un número.',
            'precio.min' => 'El precio no puede ser negativo.',
            'costo.required' => 'El costo es obligatorio.',
            'costo.numeric' => 'El costo debe ser un número.',
            'costo.min' => 'El costo no puede ser negativo.',
            'id_categoria.required' => 'El campo categoria es obligatorio.',
            'id_categoria.exists' => 'La categoría seleccionada no existe.',
            'id_empresa.required' => 'La empresa es obligatoria.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'detalles.required' => 'Agrege los detalles del producto',
            'detalles.array' => 'Los detalles deben ser un array.',
            'detalles.min' => 'Debe haber al menos un detalle.',
            'detalles.*.id_producto.required' => 'El producto es obligatorio en cada detalle.',
            'detalles.*.id_producto.exists' => 'Uno de los productos seleccionados no existe.',
            'detalles.*.cantidad.required' => 'La cantidad es obligatoria en cada detalle.',
            'detalles.*.cantidad.min' => 'La cantidad debe ser mayor a 0.',
            'id_bodega.required' => 'La bodega es obligatoria.',
            'id_bodega.exists' => 'La bodega seleccionada no existe.',
            'stock.numeric' => 'El stock debe ser un número.',
            'stock.min' => 'El stock no puede ser negativo.',
            'codigo.max' => 'El código no puede exceder 255 caracteres.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'precio' => 'precio',
            'costo' => 'costo',
            'id_categoria' => 'categoría',
            'id_empresa' => 'empresa',
            'detalles' => 'detalles',
            'id_bodega' => 'bodega',
            'stock' => 'stock',
            'codigo' => 'código',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Si codigo está vacío, establecerlo como null
        if ($this->has('codigo') && empty($this->codigo)) {
            $this->merge([
                'codigo' => null,
            ]);
        }
    }
}

