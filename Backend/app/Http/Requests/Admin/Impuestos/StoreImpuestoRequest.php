<?php

namespace App\Http\Requests\Admin\Impuestos;

use Illuminate\Foundation\Http\FormRequest;

class StoreImpuestoRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255'],
            'porcentaje' => ['required', 'numeric', 'min:0', 'max:100'],
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
            'id' => ['nullable', 'integer', 'exists:impuestos,id'],
            'id_cuenta_contable_ventas' => ['nullable', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_contable_compras' => ['nullable', 'integer', 'exists:catalogo_cuentas,id'],
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
            'porcentaje.required' => 'El porcentaje es requerido.',
            'porcentaje.numeric' => 'El porcentaje debe ser un número.',
            'porcentaje.min' => 'El porcentaje no puede ser negativo.',
            'porcentaje.max' => 'El porcentaje no puede ser mayor a 100.',
            'id_empresa.required' => 'La empresa es requerida.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'id.exists' => 'El impuesto seleccionado no existe.',
            'id_cuenta_contable_ventas.exists' => 'La cuenta contable de ventas seleccionada no existe.',
            'id_cuenta_contable_compras.exists' => 'La cuenta contable de compras seleccionada no existe.',
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
        if ($this->has('porcentaje')) {
            $this->merge(['porcentaje' => (float) $this->porcentaje]);
        }
    }
}

