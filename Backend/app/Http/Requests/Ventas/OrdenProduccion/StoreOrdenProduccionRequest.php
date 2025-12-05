<?php

namespace App\Http\Requests\Ventas\OrdenProduccion;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrdenProduccionRequest extends FormRequest
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
            'datos_orden' => 'required|json',
            'documento_pdf' => 'sometimes|nullable|file|mimes:pdf|max:5120',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'datos_orden.required' => 'Los datos de la orden son obligatorios.',
            'datos_orden.json' => 'Los datos de la orden deben ser un JSON válido.',
            'documento_pdf.file' => 'El documento PDF debe ser un archivo válido.',
            'documento_pdf.mimes' => 'El documento debe ser un archivo PDF.',
            'documento_pdf.max' => 'El documento PDF no puede exceder 5MB.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'datos_orden' => 'datos de la orden',
            'documento_pdf' => 'documento PDF',
        ];
    }
}

