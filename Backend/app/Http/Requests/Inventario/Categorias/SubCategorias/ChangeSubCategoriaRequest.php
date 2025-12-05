<?php

namespace App\Http\Requests\Inventario\Categorias\SubCategorias;

use Illuminate\Foundation\Http\FormRequest;

class ChangeSubCategoriaRequest extends FormRequest
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
            'subcategoria_anterior' => ['required', 'integer', 'exists:subcategorias,id'],
            'subcategoria_nueva' => ['required', 'integer', 'exists:subcategorias,id', 'different:subcategoria_anterior'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'subcategoria_anterior.required' => 'La subcategoría anterior es requerida.',
            'subcategoria_anterior.exists' => 'La subcategoría anterior seleccionada no existe.',
            'subcategoria_nueva.required' => 'La subcategoría nueva es requerida.',
            'subcategoria_nueva.exists' => 'La subcategoría nueva seleccionada no existe.',
            'subcategoria_nueva.different' => 'La subcategoría nueva debe ser diferente a la anterior.',
        ];
    }
}

