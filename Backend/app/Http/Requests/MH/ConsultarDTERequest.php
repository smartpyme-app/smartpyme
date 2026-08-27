<?php

namespace App\Http\Requests\MH;

use Illuminate\Foundation\Http\FormRequest;

class ConsultarDTERequest extends FormRequest
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
            'codigoGeneracion' => ['required', 'string'],
            'tdte' => ['required_without:tipoDte', 'string'],
            'tipoDte' => ['required_without:tdte', 'string'],
            'fechaEmi' => ['nullable', 'date', 'date_format:Y-m-d'],
            'ambiente' => ['nullable', 'string', 'in:00,01'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'codigoGeneracion.required' => 'El código de generación es requerido.',
            'codigoGeneracion.string' => 'El código de generación debe ser una cadena de texto.',
            'tdte.required_without' => 'El tipo DTE es requerido.',
            'tipoDte.required_without' => 'El tipo DTE es requerido.',
            'fechaEmi.date' => 'La fecha de emisión debe ser una fecha válida.',
            'fechaEmi.date_format' => 'La fecha de emisión debe tener el formato Y-m-d.',
            'ambiente.in' => 'El ambiente debe ser 00 (prueba) o 01 (producción).',
        ];
    }
}

