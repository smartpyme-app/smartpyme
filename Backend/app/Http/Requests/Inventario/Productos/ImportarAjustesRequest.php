<?php

namespace App\Http\Requests\Inventario\Productos;

use Illuminate\Foundation\Http\FormRequest;

class ImportarAjustesRequest extends FormRequest
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
            'archivo' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'detalle' => 'required|string|max:500',
            'id_bodega' => 'required|integer|exists:sucursal_bodegas,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'archivo.required' => 'El archivo es obligatorio.',
            'archivo.file' => 'El archivo debe ser válido.',
            'archivo.mimes' => 'El archivo debe ser de tipo Excel (xlsx, xls) o CSV.',
            'archivo.max' => 'El archivo no puede exceder 10MB.',
            'detalle.required' => 'El detalle es obligatorio.',
            'detalle.max' => 'El detalle no puede exceder 500 caracteres.',
            'id_bodega.required' => 'La bodega es obligatoria.',
            'id_bodega.exists' => 'La bodega seleccionada no existe.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'archivo' => 'archivo',
            'detalle' => 'detalle',
            'id_bodega' => 'bodega',
        ];
    }
}

