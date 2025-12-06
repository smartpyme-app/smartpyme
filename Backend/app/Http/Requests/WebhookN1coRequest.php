<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebhookN1coRequest extends FormRequest
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
            'orderId' => ['required', 'string'],
            'description' => ['required', 'string'],
            'level' => ['required', 'string'],
            'type' => [
                'required',
                'string',
                'in:Created,SuccessPayment,PaymentError,Cancelled,Finalized,SuccessReverse,ReverseError,Requires3ds,ThreeDSecureAuthSucceeded,ThreeDSecureAuthFailed,ThreeDSecureAuthError'
            ],
            'orderReference' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'orderId.required' => 'El orderId es requerido.',
            'description.required' => 'La descripción es requerida.',
            'level.required' => 'El nivel es requerido.',
            'type.required' => 'El tipo de evento es requerido.',
            'type.in' => 'El tipo de evento no es válido.',
            'metadata.array' => 'Los metadatos deben ser un arreglo.',
        ];
    }
}

