<?php

namespace App\Http\Requests\N1co;

use Illuminate\Foundation\Http\FormRequest;

class ProcessChargeRequest extends FormRequest
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
            'token' => ['required', 'string'],
            'customer_name' => ['required', 'string'],
            'customer_email' => ['required', 'string', 'email'],
            'customer_phone' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'card_id' => ['required', 'string'],
            'authentication_id' => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'token.required' => 'El token es requerido.',
            'customer_name.required' => 'El nombre del cliente es requerido.',
            'customer_email.required' => 'El correo electrónico del cliente es requerido.',
            'customer_email.email' => 'El correo electrónico debe ser válido.',
            'customer_phone.required' => 'El teléfono del cliente es requerido.',
            'amount.required' => 'El monto es requerido.',
            'amount.numeric' => 'El monto debe ser un número.',
            'amount.min' => 'El monto debe ser al menos 0.01.',
            'card_id.required' => 'El ID de la tarjeta es requerido.',
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

