<?php

namespace App\Http\Requests\Admin\EmpresasFuncionalidades;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarFuncionalidadRequest extends FormRequest
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
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
            'id_funcionalidad' => ['required', 'integer', 'exists:funcionalidades,id'],
            'activo' => ['required', 'boolean'],
            'configuracion' => ['nullable', 'array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id_empresa.required' => 'La empresa es requerida.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'id_funcionalidad.required' => 'La funcionalidad es requerida.',
            'id_funcionalidad.exists' => 'La funcionalidad seleccionada no existe.',
            'activo.required' => 'El estado activo es requerido.',
            'activo.boolean' => 'El estado activo debe ser un booleano.',
            'configuracion.array' => 'La configuración debe ser un arreglo.',
        ];
    }
}

