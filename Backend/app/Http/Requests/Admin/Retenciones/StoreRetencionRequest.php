<?php

namespace App\Http\Requests\Admin\Retenciones;

use Illuminate\Foundation\Http\FormRequest;

class StoreRetencionRequest extends FormRequest
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
            'id' => 'sometimes|nullable|integer|exists:retenciones,id',
            'nombre' => 'required|string|max:255',
            'porcentaje' => 'required|numeric|min:0|max:100',
            'id_empresa' => 'required|integer|exists:empresas,id',
            'id_cuenta_contable_ventas' => 'sometimes|nullable|integer|exists:cuentas,id',
            'id_cuenta_contable_compras' => 'sometimes|nullable|integer|exists:cuentas,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'La retención no existe.',
            'nombre.required' => 'El nombre de la retención es obligatorio.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'porcentaje.required' => 'El porcentaje es obligatorio.',
            'porcentaje.numeric' => 'El porcentaje debe ser un número.',
            'porcentaje.min' => 'El porcentaje no puede ser negativo.',
            'porcentaje.max' => 'El porcentaje no puede ser mayor a 100.',
            'id_empresa.required' => 'La empresa es obligatoria.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'id_cuenta_contable_ventas.exists' => 'La cuenta contable de ventas seleccionada no existe.',
            'id_cuenta_contable_compras.exists' => 'La cuenta contable de compras seleccionada no existe.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre de la retención',
            'porcentaje' => 'porcentaje',
            'id_empresa' => 'empresa',
            'id_cuenta_contable_ventas' => 'cuenta contable de ventas',
            'id_cuenta_contable_compras' => 'cuenta contable de compras',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar nombre
        if ($this->has('nombre')) {
            $this->merge([
                'nombre' => trim($this->nombre),
            ]);
        }

        // Asegurar que porcentaje sea numérico
        if ($this->has('porcentaje')) {
            $this->merge([
                'porcentaje' => (float) $this->porcentaje,
            ]);
        }
    }
}

