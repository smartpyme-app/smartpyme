<?php

namespace App\Http\Requests\Planilla;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartamentoEmpresaRequest extends FormRequest
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
            'id' => ['sometimes', 'nullable', 'integer', 'exists:departamentos_empresa,id'],
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string'],
            // El front histórico envía `estado` (1/0) sin `activo`; se normaliza en prepareForValidation.
            'activo' => ['sometimes', 'boolean'],
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
            'activo.boolean' => 'El campo activo debe ser un booleano.',
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

        if (! $this->has('activo') || $this->input('activo') === '' || $this->input('activo') === null) {
            $estado = $this->input('estado');
            $activo = ! in_array($estado, [0, '0', false, 'false'], true);
            $this->merge(['activo' => $activo]);
        }
    }
}

