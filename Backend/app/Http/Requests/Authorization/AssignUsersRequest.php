<?php

namespace App\Http\Requests\Authorization;

use Illuminate\Foundation\Http\FormRequest;

class AssignUsersRequest extends FormRequest
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
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'user_ids.required' => 'Los IDs de usuarios son requeridos.',
            'user_ids.array' => 'Los IDs de usuarios deben ser un arreglo.',
            'user_ids.min' => 'Debe haber al menos un usuario.',
            'user_ids.*.required' => 'Cada ID de usuario es requerido.',
            'user_ids.*.integer' => 'Cada ID de usuario debe ser un número entero.',
            'user_ids.*.exists' => 'Uno o más usuarios no existen.',
        ];
    }
}

