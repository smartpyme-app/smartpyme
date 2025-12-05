<?php

namespace App\Http\Requests\Inventario\Productos;

use Illuminate\Foundation\Http\FormRequest;

class BuscarPorNombreRequest extends FormRequest
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
            'nombre' => 'required|string|min:2|max:255',
            'id_empresa' => 'required|integer|exists:empresas,id',
            'limite' => 'sometimes|nullable|integer|min:1|max:20',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.min' => 'El nombre debe tener al menos 2 caracteres.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'id_empresa.required' => 'La empresa es obligatoria.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'limite.integer' => 'El límite debe ser un número entero.',
            'limite.min' => 'El límite debe ser al menos 1.',
            'limite.max' => 'El límite no puede ser mayor a 20.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'id_empresa' => 'empresa',
            'limite' => 'límite',
        ];
    }
}

