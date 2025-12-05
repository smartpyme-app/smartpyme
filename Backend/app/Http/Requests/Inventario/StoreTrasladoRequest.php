<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrasladoRequest extends FormRequest
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
            'estado' => ['required', 'string'],
            'id_producto' => ['required', 'integer', 'exists:productos,id'],
            'id_bodega_de' => ['required', 'integer', 'exists:sucursal_bodegas,id'],
            'id_bodega' => ['required', 'integer', 'exists:sucursal_bodegas,id'],
            'concepto' => ['required', 'string', 'max:255'],
            'cantidad' => ['required', 'numeric', 'min:0'],
            'id_usuario' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'estado.required' => 'El estado es requerido.',
            'id_producto.required' => 'El producto es requerido.',
            'id_producto.exists' => 'El producto seleccionado no existe.',
            'id_bodega_de.required' => 'La bodega de origen es requerida.',
            'id_bodega_de.exists' => 'La bodega de origen seleccionada no existe.',
            'id_bodega.required' => 'La bodega de destino es requerida.',
            'id_bodega.exists' => 'La bodega de destino seleccionada no existe.',
            'concepto.required' => 'El concepto es requerido.',
            'concepto.max' => 'El concepto no puede exceder 255 caracteres.',
            'cantidad.required' => 'La cantidad es requerida.',
            'cantidad.numeric' => 'La cantidad debe ser un número.',
            'cantidad.min' => 'La cantidad no puede ser negativa.',
            'id_usuario.required' => 'El usuario es requerido.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validar que las bodegas sean diferentes
            if ($this->id_bodega == $this->id_bodega_de) {
                $validator->errors()->add('id_bodega', 'Has seleccionado la misma bodega.');
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir valores numéricos
        if ($this->has('cantidad')) {
            $this->merge([
                'cantidad' => (float) $this->cantidad,
            ]);
        }
    }
}

