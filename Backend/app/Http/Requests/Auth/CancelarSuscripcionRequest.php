<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class CancelarSuscripcionRequest extends FormRequest
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
            'password' => ['required', 'string'],
            'id' => ['required', 'integer', 'exists:users,id'],
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
            'motivo_cancelacion' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'password.required' => 'La contraseña es requerida.',
            'id.required' => 'El ID del usuario es requerido.',
            'id.exists' => 'El usuario seleccionado no existe.',
            'id_empresa.required' => 'El ID de la empresa es requerido.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'motivo_cancelacion.required' => 'El motivo de cancelación es requerido.',
            'motivo_cancelacion.max' => 'El motivo de cancelación no puede exceder 500 caracteres.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar motivo_cancelacion
        if ($this->has('motivo_cancelacion')) {
            $this->merge(['motivo_cancelacion' => trim($this->motivo_cancelacion)]);
        }
    }
}

