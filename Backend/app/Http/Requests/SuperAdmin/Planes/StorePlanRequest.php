<?php

namespace App\Http\Requests\SuperAdmin\Planes;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:planes,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'precio' => ['required', 'numeric', 'min:0'],
            'id_producto' => ['required', 'integer', 'exists:productos,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'El plan seleccionado no existe.',
            'nombre.required' => 'El nombre es requerido.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'precio.required' => 'El precio es requerido.',
            'precio.numeric' => 'El precio debe ser un número.',
            'precio.min' => 'El precio debe ser mayor o igual a 0.',
            'id_producto.required' => 'El producto es requerido.',
            'id_producto.exists' => 'El producto seleccionado no existe.',
        ];
    }
}

