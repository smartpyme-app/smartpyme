<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;

class StoreAtributoRequest extends FormRequest
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
            'tipo' => ['required', 'string', 'max:255'],
            'valor' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'tipo.required' => 'El tipo es obligatorio.',
            'tipo.max' => 'El tipo no debe superar los 255 caracteres.',
            'valor.required' => 'El valor es obligatorio.',
            'valor.max' => 'El valor no debe superar los 255 caracteres.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar tipo y valor
        if ($this->has('tipo')) {
            $this->merge([
                'tipo' => trim($this->tipo),
            ]);
        }

        if ($this->has('valor')) {
            $this->merge([
                'valor' => trim($this->valor),
            ]);
        }
    }
}

