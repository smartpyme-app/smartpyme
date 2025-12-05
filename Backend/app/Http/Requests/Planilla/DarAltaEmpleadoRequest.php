<?php

namespace App\Http\Requests\Planilla;

use Illuminate\Foundation\Http\FormRequest;

class DarAltaEmpleadoRequest extends FormRequest
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
            'fecha_alta' => ['required', 'date'],
            'documento_respaldo' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:2048'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'fecha_alta.required' => 'La fecha de alta es requerida.',
            'fecha_alta.date' => 'La fecha de alta debe ser una fecha válida.',
            'documento_respaldo.mimes' => 'El documento debe ser un archivo PDF, DOC o DOCX.',
            'documento_respaldo.max' => 'El documento no puede exceder 2MB.',
        ];
    }
}

