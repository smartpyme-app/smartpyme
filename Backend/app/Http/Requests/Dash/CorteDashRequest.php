<?php

namespace App\Http\Requests\Dash;

use Illuminate\Foundation\Http\FormRequest;

class CorteDashRequest extends FormRequest
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
            'fecha' => ['required', 'date'],
            'id_sucursal' => ['nullable', 'integer', 'exists:sucursales,id'],
            'id_usuario' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha es requerida.',
            'fecha.date' => 'La fecha debe ser una fecha válida.',
            'id_sucursal.integer' => 'El ID de sucursal debe ser un número entero.',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
            'id_usuario.integer' => 'El ID de usuario debe ser un número entero.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
        ];
    }
}

