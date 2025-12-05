<?php

namespace App\Http\Requests\Planilla;

use Illuminate\Foundation\Http\FormRequest;

class StoreCargoEmpresaRequest extends FormRequest
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
            'id' => ['sometimes', 'nullable', 'integer', 'exists:cargos_de_empresa,id'],
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string'],
            'salario_base' => ['nullable', 'numeric', 'min:0'],
            'activo' => ['required', 'boolean'],
            'id_departamento' => ['nullable', 'integer', 'exists:departamentos_empresa,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es requerido.',
            'nombre.max' => 'El nombre no puede exceder 100 caracteres.',
            'salario_base.numeric' => 'El salario base debe ser un número.',
            'salario_base.min' => 'El salario base no puede ser negativo.',
            'activo.required' => 'El campo activo es requerido.',
            'activo.boolean' => 'El campo activo debe ser un booleano.',
            'id_departamento.exists' => 'El departamento seleccionado no existe.',
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

        // Convertir valores numéricos
        if ($this->has('salario_base') && $this->salario_base !== null) {
            $this->merge(['salario_base' => (float) $this->salario_base]);
        }
    }
}

