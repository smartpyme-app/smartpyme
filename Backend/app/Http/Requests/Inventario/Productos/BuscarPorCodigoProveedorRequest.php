<?php

namespace App\Http\Requests\Inventario\Productos;

use Illuminate\Foundation\Http\FormRequest;

class BuscarPorCodigoProveedorRequest extends FormRequest
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
            'cod_proveed_prod' => 'required|string|max:255',
            'id_empresa' => 'required|integer|exists:empresas,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'cod_proveed_prod.required' => 'El código de proveedor es obligatorio.',
            'cod_proveed_prod.max' => 'El código de proveedor no puede exceder 255 caracteres.',
            'id_empresa.required' => 'La empresa es obligatoria.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'cod_proveed_prod' => 'código de proveedor',
            'id_empresa' => 'empresa',
        ];
    }
}

