<?php

namespace App\Http\Requests\Admin\Empresas;

use Illuminate\Foundation\Http\FormRequest;

class StoreImagenesRequest extends FormRequest
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
            'id' => 'required|integer|exists:empresas,id',
            'type' => 'required|string|in:sello,firma,logo',
            'file' => 'required|file|image|mimes:jpeg,png,jpg,gif,svg|max:5120',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.required' => 'El ID de la empresa es obligatorio.',
            'id.exists' => 'La empresa no existe.',
            'type.required' => 'El tipo de imagen es obligatorio.',
            'type.in' => 'El tipo de imagen debe ser: sello, firma o logo.',
            'file.required' => 'El archivo de imagen es obligatorio.',
            'file.file' => 'El archivo debe ser válido.',
            'file.image' => 'El archivo debe ser una imagen.',
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
            'id' => 'empresa',
            'type' => 'tipo de imagen',
            'file' => 'imagen',
        ];
    }
}

