<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class GetStatsWhatsAppRequest extends FormRequest
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
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'empresa_id' => ['nullable', 'integer', 'exists:empresas,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'days.integer' => 'El número de días debe ser un número entero.',
            'days.min' => 'El número de días debe ser al menos 1.',
            'days.max' => 'El número de días no puede exceder 365.',
            'empresa_id.integer' => 'El ID de empresa debe ser un número entero.',
            'empresa_id.exists' => 'La empresa seleccionada no existe.',
        ];
    }
}

