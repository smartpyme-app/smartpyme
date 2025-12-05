<?php

namespace App\Http\Requests\Planilla;

use Illuminate\Foundation\Http\FormRequest;

class DarBajaEmpleadoRequest extends FormRequest
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
            'fecha_fin' => ['required', 'date'],
            'fecha_baja' => ['required', 'date'],
            'tipo_baja' => ['required', 'string', 'in:Renuncia,Despido,Terminación de contrato'],
            'motivo' => ['required', 'string'],
            'documento_respaldo' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:2048'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'fecha_fin.required' => 'La fecha de notificación es requerida.',
            'fecha_fin.date' => 'La fecha de notificación debe ser una fecha válida.',
            'fecha_baja.required' => 'La fecha efectiva de baja es requerida.',
            'fecha_baja.date' => 'La fecha efectiva de baja debe ser una fecha válida.',
            'tipo_baja.required' => 'El tipo de baja es requerido.',
            'tipo_baja.in' => 'El tipo de baja debe ser: Renuncia, Despido o Terminación de contrato.',
            'motivo.required' => 'El motivo es requerido.',
            'documento_respaldo.mimes' => 'El documento debe ser un archivo PDF, DOC o DOCX.',
            'documento_respaldo.max' => 'El documento no puede exceder 2MB.',
        ];
    }
}

