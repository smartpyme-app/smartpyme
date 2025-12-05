<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComboProductoRequest extends FormRequest
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
            'id' => ['required', 'integer', 'exists:combos_productos,id'],
            'codigo_combo' => [
                'required',
                'string',
                Rule::unique('combos_productos', 'codigo_combo')->ignore($this->id),
            ],
            'descripcion' => ['required', 'string'],
            'nombre' => ['required', 'string', 'max:255'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id_producto' => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.precio' => ['required', 'numeric', 'min:0'],
            'detalles.*.costo' => ['required', 'numeric', 'min:0'],
            'precio_final' => ['nullable', 'numeric', 'min:0'],
            'id_bodega' => ['required', 'integer', 'exists:sucursal_bodegas,id'],
            'cantidad' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.required' => 'El ID del combo es requerido.',
            'id.exists' => 'El combo seleccionado no existe.',
            'codigo_combo.required' => 'El código del combo es requerido.',
            'codigo_combo.unique' => 'Un combo con este código ya fue registrado anteriormente.',
            'descripcion.required' => 'La descripción es requerida.',
            'nombre.required' => 'El nombre es requerido.',
            'detalles.required' => 'Los detalles son requeridos.',
            'detalles.array' => 'Los detalles deben ser un arreglo.',
            'detalles.min' => 'Debe haber al menos un detalle.',
            'detalles.*.id_producto.required' => 'El producto es requerido para cada detalle.',
            'detalles.*.id_producto.exists' => 'Uno o más productos no existen.',
            'detalles.*.cantidad.required' => 'La cantidad es requerida para cada detalle.',
            'detalles.*.cantidad.min' => 'La cantidad debe ser mayor a 0.',
            'detalles.*.precio.required' => 'El precio es requerido para cada detalle.',
            'detalles.*.precio.min' => 'El precio no puede ser negativo.',
            'detalles.*.costo.required' => 'El costo es requerido para cada detalle.',
            'detalles.*.costo.min' => 'El costo no puede ser negativo.',
            'precio_final.min' => 'El precio final no puede ser negativo.',
            'cantidad.required' => 'La cantidad es requerida.',
            'cantidad.min' => 'La cantidad no puede ser negativa.',
            'id_bodega.required' => 'La bodega es requerida.',
            'id_bodega.exists' => 'La bodega seleccionada no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar strings
        if ($this->has('nombre')) {
            $this->merge(['nombre' => trim($this->nombre)]);
        }

        if ($this->has('descripcion')) {
            $this->merge(['descripcion' => trim($this->descripcion)]);
        }

        // Convertir valores numéricos
        if ($this->has('precio_final')) {
            $this->merge(['precio_final' => (float) $this->precio_final]);
        }

        if ($this->has('cantidad')) {
            $this->merge(['cantidad' => (float) $this->cantidad]);
        }
    }
}

