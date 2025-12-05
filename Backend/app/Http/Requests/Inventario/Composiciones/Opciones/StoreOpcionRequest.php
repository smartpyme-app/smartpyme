<?php

namespace App\Http\Requests\Inventario\Composiciones\Opciones;

use Illuminate\Foundation\Http\FormRequest;

class StoreOpcionRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:composicion_opciones,id'],
            'id_producto' => ['required', 'integer', 'exists:productos,id'],
            'id_composicion' => ['required', 'integer', 'exists:composiciones,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'La opción seleccionada no existe.',
            'id_producto.required' => 'El producto es requerido.',
            'id_producto.exists' => 'El producto seleccionado no existe.',
            'id_composicion.required' => 'La composición es requerida.',
            'id_composicion.exists' => 'La composición seleccionada no existe.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validar que no exista otra opción para el mismo producto y composición (solo si es nuevo)
            if (!$this->filled('id')) {
                $existe = \App\Models\Inventario\Composiciones\Opcion::where('id_producto', $this->id_producto)
                    ->where('id_composicion', $this->id_composicion)
                    ->exists();

                if ($existe) {
                    $validator->errors()->add('id_producto', 'Ya ha sido agregado el producto a esta composición.');
                }
            }
        });
    }
}

