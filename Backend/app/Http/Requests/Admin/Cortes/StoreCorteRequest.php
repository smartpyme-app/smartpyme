<?php

namespace App\Http\Requests\Admin\Cortes;

use Illuminate\Foundation\Http\FormRequest;

class StoreCorteRequest extends FormRequest
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
            'apertura' => ['required', 'numeric', 'min:0'],
            'fecha' => ['required', 'date'],
            'caja_id' => ['required', 'integer', 'exists:cajas,id'],
            'usuario_id' => ['required', 'integer', 'exists:users,id'],
            'id' => ['nullable', 'integer', 'exists:caja_cortes,id'],
            'cierre' => ['nullable', 'numeric', 'min:0'],
            'saldo_inicial' => ['nullable', 'numeric', 'min:0'],
            'saldo_final' => ['nullable', 'numeric', 'min:0'],
            'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'apertura.required' => 'El saldo de apertura es requerido.',
            'apertura.numeric' => 'El saldo de apertura debe ser un número.',
            'apertura.min' => 'El saldo de apertura no puede ser negativo.',
            'fecha.required' => 'La fecha es requerida.',
            'fecha.date' => 'La fecha debe ser una fecha válida.',
            'caja_id.required' => 'La caja es requerida.',
            'caja_id.exists' => 'La caja seleccionada no existe.',
            'usuario_id.required' => 'El usuario es requerido.',
            'usuario_id.exists' => 'El usuario seleccionado no existe.',
            'id.exists' => 'El corte seleccionado no existe.',
            'cierre.numeric' => 'El cierre debe ser un número.',
            'cierre.min' => 'El cierre no puede ser negativo.',
            'saldo_inicial.numeric' => 'El saldo inicial debe ser un número.',
            'saldo_inicial.min' => 'El saldo inicial no puede ser negativo.',
            'saldo_final.numeric' => 'El saldo final debe ser un número.',
            'saldo_final.min' => 'El saldo final no puede ser negativo.',
            'supervisor_id.exists' => 'El supervisor seleccionado no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir valores numéricos
        if ($this->has('apertura')) {
            $this->merge(['apertura' => (float) $this->apertura]);
        }

        if ($this->has('cierre')) {
            $this->merge(['cierre' => (float) $this->cierre]);
        }

        if ($this->has('saldo_inicial')) {
            $this->merge(['saldo_inicial' => (float) $this->saldo_inicial]);
        }

        if ($this->has('saldo_final')) {
            $this->merge(['saldo_final' => (float) $this->saldo_final]);
        }
    }
}

