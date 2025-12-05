<?php

namespace App\Http\Requests\Inventario\Proveedor;

use Illuminate\Foundation\Http\FormRequest;

class BuscarPorNitProveedorRequest extends FormRequest
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
            'nit' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nit.required' => 'El NIT es requerido.',
            'nit.string' => 'El NIT debe ser una cadena de texto.',
            'nit.max' => 'El NIT no puede exceder 255 caracteres.',
        ];
    }
}

