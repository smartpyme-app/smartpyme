<?php

namespace App\Http\Requests\Admin\Usuarios;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAuthCodeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // La autorización compleja se maneja en el controlador con checkAuth()
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'codigo_autorizacion' => 'required|numeric|digits_between:3,10',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'codigo_autorizacion.required' => 'El código de autorización es obligatorio.',
            'codigo_autorizacion.numeric' => 'El código de autorización debe ser numérico.',
            'codigo_autorizacion.digits_between' => 'El código de autorización debe tener entre 3 y 10 dígitos.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'codigo_autorizacion' => 'código de autorización',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Asegurar que el código sea numérico (remover espacios y caracteres no numéricos)
        if ($this->has('codigo_autorizacion')) {
            $this->merge([
                'codigo_autorizacion' => preg_replace('/[^0-9]/', '', $this->codigo_autorizacion),
            ]);
        }
    }
}

