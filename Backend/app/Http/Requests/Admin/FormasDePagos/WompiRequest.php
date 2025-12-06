<?php

namespace App\Http\Requests\Admin\FormasDePagos;

use Illuminate\Foundation\Http\FormRequest;

class WompiRequest extends FormRequest
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
            'wompi_id' => ['required', 'string', 'max:255'],
            'wompi_aplicativo' => ['required', 'string', 'max:255'],
            'wompi_secret' => ['required', 'string', 'max:255'],
            'id' => ['required', 'integer', 'exists:empresas,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'wompi_id.required' => 'El ID de Wompi es requerido.',
            'wompi_id.max' => 'El ID de Wompi no puede exceder 255 caracteres.',
            'wompi_aplicativo.required' => 'El aplicativo de Wompi es requerido.',
            'wompi_aplicativo.max' => 'El aplicativo de Wompi no puede exceder 255 caracteres.',
            'wompi_secret.required' => 'El secreto de Wompi es requerido.',
            'wompi_secret.max' => 'El secreto de Wompi no puede exceder 255 caracteres.',
            'id.required' => 'El ID de la empresa es requerido.',
            'id.exists' => 'La empresa seleccionada no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar strings
        if ($this->has('wompi_id')) {
            $this->merge(['wompi_id' => trim($this->wompi_id)]);
        }

        if ($this->has('wompi_aplicativo')) {
            $this->merge(['wompi_aplicativo' => trim($this->wompi_aplicativo)]);
        }

        if ($this->has('wompi_secret')) {
            $this->merge(['wompi_secret' => trim($this->wompi_secret)]);
        }
    }
}

