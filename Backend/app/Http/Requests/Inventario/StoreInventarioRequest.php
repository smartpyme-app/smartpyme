<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Inventario\Inventario;

class StoreInventarioRequest extends FormRequest
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
            'id' => ['sometimes', 'nullable', 'integer', 'exists:inventario,id'],
            'id_producto' => ['required', 'integer', 'exists:productos,id'],
            'id_sucursal' => ['nullable', 'integer'],
            'id_bodega' => ['required', 'integer', 'exists:sucursal_bodegas,id'],
            'stock' => ['required', 'numeric', 'min:0'],
            'stock_minimo' => ['required', 'numeric', 'min:0'],
            'stock_maximo' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id_producto.required' => 'El producto es requerido.',
            'id_producto.exists' => 'El producto seleccionado no existe.',
            'id_bodega.required' => 'La bodega es requerida.',
            'id_bodega.exists' => 'La bodega seleccionada no existe.',
            'stock.required' => 'El stock es requerido.',
            'stock.numeric' => 'El stock debe ser un número.',
            'stock.min' => 'El stock no puede ser negativo.',
            'stock_minimo.required' => 'El stock mínimo es requerido.',
            'stock_minimo.numeric' => 'El stock mínimo debe ser un número.',
            'stock_minimo.min' => 'El stock mínimo no puede ser negativo.',
            'stock_maximo.required' => 'El stock máximo es requerido.',
            'stock_maximo.numeric' => 'El stock máximo debe ser un número.',
            'stock_maximo.min' => 'El stock máximo no puede ser negativo.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validar que no exista un inventario duplicado para el mismo producto y bodega
            if (!$this->id) {
                $existe = Inventario::where('id_producto', $this->id_producto)
                    ->where('id_bodega', $this->id_bodega)
                    ->first();

                if ($existe) {
                    $validator->errors()->add('id_producto', 'Ya ha sido configurado el producto en esta bodega');
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir valores numéricos
        if ($this->has('stock')) {
            $this->merge([
                'stock' => (float) $this->stock,
            ]);
        }

        if ($this->has('stock_minimo')) {
            $this->merge([
                'stock_minimo' => (float) $this->stock_minimo,
            ]);
        }

        if ($this->has('stock_maximo')) {
            $this->merge([
                'stock_maximo' => (float) $this->stock_maximo,
            ]);
        }
    }
}

