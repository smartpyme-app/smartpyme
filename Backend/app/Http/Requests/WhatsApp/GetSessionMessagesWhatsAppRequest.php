<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class GetSessionMessagesWhatsAppRequest extends FormRequest
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
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'per_page.integer' => 'El número de registros por página debe ser un entero.',
            'per_page.min' => 'El número de registros por página debe ser al menos 1.',
            'per_page.max' => 'El número de registros por página no puede exceder 200.',
        ];
    }
}

