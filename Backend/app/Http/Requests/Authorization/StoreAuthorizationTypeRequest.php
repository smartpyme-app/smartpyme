<?php

namespace App\Http\Requests\Authorization;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAuthorizationTypeRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                Rule::unique('authorization_types', 'name')->ignore($this->id),
            ],
            'display_name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'conditions' => ['nullable', 'array'],
            'expiration_hours' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es requerido.',
            'name.unique' => 'El nombre ya está en uso.',
            'display_name.required' => 'El nombre para mostrar es requerido.',
            'expiration_hours.required' => 'Las horas de expiración son requeridas.',
            'expiration_hours.integer' => 'Las horas de expiración deben ser un número entero.',
            'expiration_hours.min' => 'Las horas de expiración deben ser al menos 1.',
            'conditions.array' => 'Las condiciones deben ser un arreglo.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar strings
        if ($this->has('name')) {
            $this->merge(['name' => trim($this->name)]);
        }

        if ($this->has('display_name')) {
            $this->merge(['display_name' => trim($this->display_name)]);
        }

        if ($this->has('description')) {
            $this->merge(['description' => trim($this->description)]);
        }
    }
}

