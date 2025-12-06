<?php

namespace App\Http\Requests\Compras\Proveedores;

use Illuminate\Foundation\Http\FormRequest;

class ImportProveedoresRequest extends FormRequest
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
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'file.required' => 'El archivo es obligatorio.',
            'file.file' => 'El archivo debe ser válido.',
            'file.mimes' => 'El archivo debe ser de tipo Excel (xlsx, xls) o CSV.',
            'file.max' => 'El archivo no puede exceder 10MB.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'file' => 'archivo',
        ];
    }
}

