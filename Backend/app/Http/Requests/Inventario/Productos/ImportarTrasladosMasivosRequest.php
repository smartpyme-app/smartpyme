<?php

namespace App\Http\Requests\Inventario\Productos;

use Illuminate\Foundation\Http\FormRequest;

class ImportarTrasladosMasivosRequest extends FormRequest
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
            'concepto' => 'required|string|max:500',
            'id_bodega_origen' => 'required|integer|exists:sucursal_bodegas,id',
            'id_bodega_destino' => 'required|integer|exists:sucursal_bodegas,id|different:id_bodega_origen',
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
            'concepto.required' => 'El concepto es obligatorio.',
            'concepto.max' => 'El concepto no puede exceder 500 caracteres.',
            'id_bodega_origen.required' => 'La bodega de origen es obligatoria.',
            'id_bodega_origen.exists' => 'La bodega de origen seleccionada no existe.',
            'id_bodega_destino.required' => 'La bodega de destino es obligatoria.',
            'id_bodega_destino.exists' => 'La bodega de destino seleccionada no existe.',
            'id_bodega_destino.different' => 'La bodega de destino debe ser diferente a la bodega de origen.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'archivo' => 'archivo',
            'concepto' => 'concepto',
            'id_bodega_origen' => 'bodega de origen',
            'id_bodega_destino' => 'bodega de destino',
        ];
    }
}

