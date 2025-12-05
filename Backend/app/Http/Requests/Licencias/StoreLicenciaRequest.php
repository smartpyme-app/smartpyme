<?php

namespace App\Http\Requests\Licencias;

use Illuminate\Foundation\Http\FormRequest;

class StoreLicenciaRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:licencias,id'],
            'num_licencias' => ['required', 'integer', 'min:1'],
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'La licencia seleccionada no existe.',
            'num_licencias.required' => 'El número de licencias es requerido.',
            'num_licencias.integer' => 'El número de licencias debe ser un número entero.',
            'num_licencias.min' => 'El número de licencias debe ser al menos 1.',
            'id_empresa.required' => 'El campo empresa es obligatorio.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
        ];
    }
}

