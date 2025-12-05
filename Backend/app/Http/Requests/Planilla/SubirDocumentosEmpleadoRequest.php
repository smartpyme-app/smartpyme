<?php

namespace App\Http\Requests\Planilla;

use Illuminate\Foundation\Http\FormRequest;

class SubirDocumentosEmpleadoRequest extends FormRequest
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
            'archivo' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:2048'],
            'tipo_documento' => ['required'],
            'fecha_documento' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'archivo.required' => 'El archivo es requerido.',
            'archivo.file' => 'El archivo debe ser un archivo válido.',
            'archivo.mimes' => 'El archivo debe ser un archivo PDF, DOC o DOCX.',
            'archivo.max' => 'El archivo no puede exceder 2MB.',
            'tipo_documento.required' => 'El tipo de documento es requerido.',
            'fecha_documento.required' => 'La fecha del documento es requerida.',
            'fecha_documento.date' => 'La fecha del documento debe ser una fecha válida.',
            'fecha_vencimiento.date' => 'La fecha de vencimiento debe ser una fecha válida.',
        ];
    }
}

