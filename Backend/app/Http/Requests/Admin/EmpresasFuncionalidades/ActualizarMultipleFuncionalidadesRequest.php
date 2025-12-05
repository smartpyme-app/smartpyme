<?php

namespace App\Http\Requests\Admin\EmpresasFuncionalidades;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarMultipleFuncionalidadesRequest extends FormRequest
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
            'funcionalidades' => ['required', 'array', 'min:1'],
            'funcionalidades.*.id' => ['required', 'integer', 'exists:funcionalidades,id'],
            'funcionalidades.*.activo' => ['required', 'boolean'],
            'funcionalidades.*.configuracion' => ['nullable', 'array'],
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
            'funcionalidades.required' => 'Las funcionalidades son requeridas.',
            'funcionalidades.array' => 'Las funcionalidades deben ser un arreglo.',
            'funcionalidades.min' => 'Debe haber al menos una funcionalidad.',
            'funcionalidades.*.id.required' => 'El ID de la funcionalidad es requerido.',
            'funcionalidades.*.id.exists' => 'Uno o más IDs de funcionalidades no existen.',
            'funcionalidades.*.activo.required' => 'El estado activo es requerido para cada funcionalidad.',
            'funcionalidades.*.activo.boolean' => 'El estado activo debe ser un booleano.',
            'funcionalidades.*.configuracion.array' => 'La configuración debe ser un arreglo.',
        ];
    }
}

