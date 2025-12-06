<?php

namespace App\Http\Requests\Admin\Empresas;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePagoRecurrenteRequest extends FormRequest
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
            'id_empresa' => 'required|integer|exists:empresas,id',
            'pago_recurrente' => 'required|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id_empresa.required' => 'El ID de la empresa es obligatorio.',
            'id_empresa.integer' => 'El ID de la empresa debe ser un número entero.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'pago_recurrente.required' => 'El estado de pago recurrente es obligatorio.',
            'pago_recurrente.boolean' => 'El pago recurrente debe ser verdadero o falso.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'id_empresa' => 'empresa',
            'pago_recurrente' => 'pago recurrente',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir pago_recurrente a boolean
        if ($this->has('pago_recurrente')) {
            $this->merge([
                'pago_recurrente' => filter_var($this->pago_recurrente, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}

