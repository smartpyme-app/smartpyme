<?php

namespace App\Http\Requests\Inventario\Productos;

use Illuminate\Foundation\Http\FormRequest;

class BuscarSugerenciasRequest extends FormRequest
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
            'termino' => 'required|string|min:2|max:255',
            'palabras' => 'sometimes|nullable|array',
            'palabras.*' => 'string|min:2|max:255',
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
            'termino.required' => 'El término de búsqueda es obligatorio.',
            'termino.min' => 'El término de búsqueda debe tener al menos 2 caracteres.',
            'termino.max' => 'El término de búsqueda no puede exceder 255 caracteres.',
            'palabras.array' => 'Las palabras deben ser un array.',
            'palabras.*.string' => 'Cada palabra debe ser una cadena de texto.',
            'palabras.*.min' => 'Cada palabra debe tener al menos 2 caracteres.',
            'palabras.*.max' => 'Cada palabra no puede exceder 255 caracteres.',
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
            'termino' => 'término de búsqueda',
            'palabras' => 'palabras',
            'id_empresa' => 'empresa',
            'limite' => 'límite',
        ];
    }
}

