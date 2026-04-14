<?php

namespace App\Http\Requests\Compras\Gastos;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoriaRequest extends FormRequest
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
            'id' => ['sometimes', 'nullable', 'integer', 'exists:gastos_categorias,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
            'id_cuenta_contable' => ['nullable', 'integer', 'exists:catalogo_cuentas,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es requerido.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'id_empresa.required' => 'La empresa es requerida.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'id_cuenta_contable.exists' => 'La cuenta contable seleccionada no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // La empresa siempre es la del usuario autenticado (el front no siempre envía id_empresa).
        if (auth()->check() && ! $this->filled('id_empresa')) {
            $this->merge([
                'id_empresa' => (int) auth()->user()->id_empresa,
            ]);
        }

        // Sanitizar nombre
        if ($this->has('nombre')) {
            $this->merge([
                'nombre' => trim($this->nombre),
            ]);
        }
    }
}

