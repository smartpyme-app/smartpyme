<?php

namespace App\Http\Requests\Admin\Accesos;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccesoRequest extends FormRequest
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
            'usuario_id' => ['required', 'integer', 'exists:users,id'],
            'id' => ['nullable', 'integer', 'exists:accesos,id'],
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
            'usuario_id.required' => 'El usuario es requerido.',
            'usuario_id.exists' => 'El usuario seleccionado no existe.',
            'id.exists' => 'El acceso seleccionado no existe.',
        ];
    }
}

