<?php

namespace App\Http\Requests\Compras\Gastos;

use Illuminate\Foundation\Http\FormRequest;

class ImportarJsonGastoRequest extends FormRequest
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
            'json_data' => 'required|json',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'json_data.required' => 'Los datos JSON son obligatorios.',
            'json_data.json' => 'Los datos deben ser un JSON válido.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'json_data' => 'datos JSON',
        ];
    }
}

