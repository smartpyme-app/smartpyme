<?php

namespace App\Http\Requests\N1co;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentMethodRequest extends FormRequest
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
            'customer.id' => ['required', 'integer'],
            'customer.name' => ['required', 'string'],
            'customer.email' => ['required', 'string', 'email'],
            'customer.phoneNumber' => ['nullable', 'string'],
            'card.number' => ['required', 'string', 'min:13', 'max:16'],
            'card.expirationMonth' => ['required', 'string', 'size:2', 'in:01,02,03,04,05,06,07,08,09,10,11,12'],
            'card.expirationYear' => ['required', 'string', 'size:2'],
            'card.cvv' => ['required', 'string', 'min:3', 'max:4'],
            'card.cardHolder' => ['required', 'string', 'min:3'],
            'billingInfo.countryCode' => ['nullable', 'string'],
            'billingInfo.stateCode' => ['nullable', 'string'],
            'billingInfo.zipCode' => ['nullable', 'string'],
            'forceNewPaymentMethod' => ['nullable', 'boolean'],
            'updatePaymentMethod' => ['nullable', 'boolean'],
            'showPaymentForm' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'customer.id.required' => 'El ID del cliente es requerido.',
            'customer.id.integer' => 'El ID del cliente debe ser un número entero.',
            'customer.name.required' => 'El nombre del cliente es requerido.',
            'customer.email.required' => 'El correo electrónico del cliente es requerido.',
            'customer.email.email' => 'El correo electrónico debe ser válido.',
            'card.number.required' => 'El número de tarjeta es requerido.',
            'card.number.min' => 'El número de tarjeta debe tener al menos 13 dígitos.',
            'card.number.max' => 'El número de tarjeta no puede exceder 16 dígitos.',
            'card.expirationMonth.required' => 'El mes de expiración es requerido.',
            'card.expirationMonth.size' => 'El mes de expiración debe tener 2 dígitos.',
            'card.expirationMonth.in' => 'El mes de expiración debe ser válido (01-12).',
            'card.expirationYear.required' => 'El año de expiración es requerido.',
            'card.expirationYear.size' => 'El año de expiración debe tener 2 dígitos.',
            'card.cvv.required' => 'El CVV es requerido.',
            'card.cvv.min' => 'El CVV debe tener al menos 3 dígitos.',
            'card.cvv.max' => 'El CVV no puede exceder 4 dígitos.',
            'card.cardHolder.required' => 'El nombre del titular es requerido.',
            'card.cardHolder.min' => 'El nombre del titular debe tener al menos 3 caracteres.',
        ];
    }
}

