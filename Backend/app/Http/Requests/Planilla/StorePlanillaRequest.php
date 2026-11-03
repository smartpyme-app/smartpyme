<?php

namespace App\Http\Requests\Planilla;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanillaRequest extends FormRequest
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
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date'],
            'tipo_planilla' => ['required', 'string', 'in:quincenal,mensual,semanal'],
            'planillaTemplate' => ['nullable', 'integer', 'exists:planillas,id'],
            'id_cuenta_banco' => ['nullable', 'integer', 'exists:cuentas_bancarias,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('id_cuenta_banco') === '' || $this->input('id_cuenta_banco') === 'null') {
            $this->merge(['id_cuenta_banco' => null]);
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'fecha_inicio.required' => 'La fecha de inicio es requerida.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_fin.required' => 'La fecha de fin es requerida.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'tipo_planilla.required' => 'El tipo de planilla es requerido.',
            'tipo_planilla.in' => 'El tipo de planilla debe ser: quincenal, mensual o semanal.',
            'planillaTemplate.exists' => 'La planilla plantilla seleccionada no existe.',
        ];
    }
}
