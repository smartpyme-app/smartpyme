<?php

namespace App\Http\Requests\SuperAdmin\Transacciones;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransaccionRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:orden_pagos,id'],
            'fecha' => ['required', 'date'],
            'total' => ['required', 'numeric', 'min:0'],
            'empresa_id' => ['required', 'integer', 'exists:empresas,id'],
            'usuario_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'La transacción seleccionada no existe.',
            'fecha.required' => 'La fecha es requerida.',
            'fecha.date' => 'La fecha debe ser una fecha válida.',
            'total.required' => 'El total es requerido.',
            'total.numeric' => 'El total debe ser un número.',
            'total.min' => 'El total debe ser mayor o igual a 0.',
            'empresa_id.required' => 'La empresa es requerida.',
            'empresa_id.exists' => 'La empresa seleccionada no existe.',
            'usuario_id.required' => 'El usuario es requerido.',
            'usuario_id.exists' => 'El usuario seleccionado no existe.',
        ];
    }
}

