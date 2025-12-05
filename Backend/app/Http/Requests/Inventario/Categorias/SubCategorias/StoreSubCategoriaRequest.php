<?php

namespace App\Http\Requests\Inventario\Categorias\SubCategorias;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubCategoriaRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:subcategorias,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
            'tipo_comision' => ['nullable', 'string'],
            'comision' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'La subcategoría seleccionada no existe.',
            'nombre.required' => 'El nombre es requerido.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'categoria_id.required' => 'La categoría es requerida.',
            'categoria_id.exists' => 'La categoría seleccionada no existe.',
            'comision.numeric' => 'La comisión debe ser un número.',
            'comision.min' => 'La comisión debe ser mayor o igual a 0.',
        ];
    }
}

