<?php

namespace App\Http\Requests\N1co;

use Illuminate\Foundation\Http\FormRequest;

class ProcessChargeReadyRequest extends FormRequest
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
            'metodo_pago_id' => ['required', 'integer', 'exists:metodos_pago,id'],
            'id_usuario' => ['required', 'integer', 'exists:users,id'],
            'empresa_id' => ['required', 'integer', 'exists:empresas,id'],
            'plan_id' => ['required', 'integer', 'exists:planes,id'],
            'customer_name' => ['required', 'string'],
            'customer_email' => ['required', 'string', 'email'],
            'customer_phone' => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'metodo_pago_id.required' => 'El método de pago es requerido.',
            'metodo_pago_id.exists' => 'El método de pago seleccionado no existe.',
            'id_usuario.required' => 'El usuario es requerido.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'empresa_id.required' => 'La empresa es requerida.',
            'empresa_id.exists' => 'La empresa seleccionada no existe.',
            'plan_id.required' => 'El plan es requerido.',
            'plan_id.exists' => 'El plan seleccionado no existe.',
            'customer_name.required' => 'El nombre del cliente es requerido.',
            'customer_email.required' => 'El correo electrónico del cliente es requerido.',
            'customer_email.email' => 'El correo electrónico debe ser válido.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar strings
        if ($this->has('customer_name')) {
            $this->merge(['customer_name' => trim($this->customer_name)]);
        }

        if ($this->has('customer_email')) {
            $this->merge(['customer_email' => strtolower(trim($this->customer_email))]);
        }
    }
}

