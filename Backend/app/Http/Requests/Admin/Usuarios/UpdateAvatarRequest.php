<?php

namespace App\Http\Requests\Admin\Usuarios;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAvatarRequest extends FormRequest
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
            'id' => 'required|integer|exists:users,id',
            'file' => 'required|file|mimes:jpeg,png,jpg,gif,svg|max:5120',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.required' => 'El ID del usuario es obligatorio.',
            'id.exists' => 'El usuario no existe.',
            'file.required' => 'El archivo de imagen es obligatorio.',
            'file.file' => 'El archivo debe ser válido.',
            'file.mimes' => 'La imagen debe ser de tipo: jpeg, png, jpg, gif o svg.',
            'file.max' => 'La imagen no puede exceder 5MB.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'id' => 'usuario',
            'file' => 'imagen',
        ];
    }
}

