<?php

namespace App\Http\Requests\Authorization;

use Illuminate\Foundation\Http\FormRequest;

class RequestAuthorizationRequest extends FormRequest
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
            'type' => ['required', 'string'],
            'model_type' => ['required', 'string'],
            'model_id' => ['nullable', 'integer'],
            'description' => ['required', 'string'],
            'data' => ['nullable', 'array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'type.required' => 'El tipo de autorización es requerido.',
            'model_type.required' => 'El tipo de modelo es requerido.',
            'description.required' => 'La descripción es requerida.',
            'data.array' => 'Los datos deben ser un arreglo.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar description
        if ($this->has('description')) {
            $this->merge(['description' => trim($this->description)]);
        }
    }
}

