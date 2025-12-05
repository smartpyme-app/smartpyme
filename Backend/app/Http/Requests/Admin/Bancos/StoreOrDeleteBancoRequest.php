<?php

namespace App\Http\Requests\Admin\Bancos;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrDeleteBancoRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:150'],
            'orden' => ['nullable', 'numeric'],
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del banco es requerido.',
            'nombre.max' => 'El nombre del banco no puede exceder 150 caracteres.',
            'orden.numeric' => 'El orden debe ser un número.',
            'id_empresa.required' => 'La empresa es requerida.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar strings
        if ($this->has('nombre')) {
            $this->merge(['nombre' => trim($this->nombre)]);
        }

        // Convertir valores numéricos
        if ($this->has('orden')) {
            $this->merge(['orden' => (int) $this->orden]);
        }
    }
}

