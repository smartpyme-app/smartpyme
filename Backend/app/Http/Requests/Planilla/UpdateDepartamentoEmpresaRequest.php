<?php

namespace App\Http\Requests\Planilla;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartamentoEmpresaRequest extends FormRequest
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
            'id' => ['required', 'integer', 'exists:departamentos_empresa,id'],
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string'],
            'estado' => ['required', 'integer', 'in:0,1'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.required' => 'El ID es requerido.',
            'id.exists' => 'El departamento seleccionado no existe.',
            'nombre.required' => 'El nombre es requerido.',
            'nombre.max' => 'El nombre no puede exceder 100 caracteres.',
            'estado.required' => 'El estado es requerido.',
            'estado.integer' => 'El estado debe ser un número entero.',
            'estado.in' => 'El estado debe ser 0 o 1.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar nombre
        if ($this->has('nombre')) {
            $this->merge(['nombre' => trim($this->nombre)]);
        }
    }
}

