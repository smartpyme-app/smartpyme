<?php

namespace App\Http\Requests\Admin\MHPruebasMasivas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EjecutarPruebasMasivasRequest extends FormRequest
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
            'tipo' => [
                'required',
                'string',
                Rule::in(['facturas', 'creditosFiscales', 'notasCredito', 'notasDebito', 'facturasExportacion', 'sujetoExcluido'])
            ],
            'cantidad' => ['required', 'integer', 'min:1', 'max:100'],
            'id_documento_base' => ['nullable', 'integer', 'exists:ventas,id'],
            'correlativo_inicial' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'tipo.required' => 'El tipo de documento es requerido.',
            'tipo.in' => 'El tipo de documento no es válido.',
            'cantidad.required' => 'La cantidad es requerida.',
            'cantidad.integer' => 'La cantidad debe ser un número entero.',
            'cantidad.min' => 'La cantidad debe ser al menos 1.',
            'cantidad.max' => 'La cantidad no puede ser mayor a 100.',
            'id_documento_base.exists' => 'El documento base seleccionado no existe.',
            'correlativo_inicial.integer' => 'El correlativo inicial debe ser un número entero.',
            'correlativo_inicial.min' => 'El correlativo inicial debe ser al menos 1.',
        ];
    }
}

