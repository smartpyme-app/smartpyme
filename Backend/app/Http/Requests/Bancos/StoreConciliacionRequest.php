<?php

namespace App\Http\Requests\Bancos;

use Illuminate\Foundation\Http\FormRequest;

class StoreConciliacionRequest extends FormRequest
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
            'id' => ['sometimes', 'nullable', 'integer', 'exists:cuentas_bancarias_conciliaciones,id'],
            'fecha' => ['required', 'date'],
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
            'id_cuenta' => ['required', 'integer', 'exists:cuentas_bancarias,id'],
            'saldo_anterior' => ['nullable', 'numeric'],
            'saldo_actual' => ['required', 'numeric'],
            'nota' => ['sometimes', 'nullable', 'string', 'max:255'],
            'id_usuario' => ['required', 'integer', 'exists:users,id'],
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha es requerida.',
            'fecha.date' => 'La fecha debe ser una fecha válida.',
            'desde.required' => 'La fecha desde es requerida.',
            'desde.date' => 'La fecha desde debe ser una fecha válida.',
            'hasta.required' => 'La fecha hasta es requerida.',
            'hasta.date' => 'La fecha hasta debe ser una fecha válida.',
            'hasta.after_or_equal' => 'La fecha hasta debe ser posterior o igual a la fecha desde.',
            'id_cuenta.required' => 'La cuenta bancaria es requerida.',
            'id_cuenta.exists' => 'La cuenta bancaria seleccionada no existe.',
            'saldo_actual.required' => 'El saldo actual es requerido.',
            'saldo_actual.numeric' => 'El saldo actual debe ser un número.',
            'saldo_anterior.numeric' => 'El saldo anterior debe ser un número.',
            'nota.max' => 'La nota no puede exceder 255 caracteres.',
            'id_usuario.required' => 'El usuario es requerido.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'id_empresa.required' => 'La empresa es requerida.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar nota
        if ($this->has('nota')) {
            $this->merge(['nota' => trim($this->nota)]);
        }

        // Convertir valores numéricos
        if ($this->has('saldo_actual')) {
            $this->merge(['saldo_actual' => (float) $this->saldo_actual]);
        }

        if ($this->has('saldo_anterior')) {
            $this->merge(['saldo_anterior' => (float) $this->saldo_anterior]);
        }
    }
}

