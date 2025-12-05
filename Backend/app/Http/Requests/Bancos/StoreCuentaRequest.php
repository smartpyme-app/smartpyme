<?php

namespace App\Http\Requests\Bancos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCuentaRequest extends FormRequest
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
            'id' => ['sometimes', 'nullable', 'integer', 'exists:cuentas_bancarias,id'],
            'numero' => [
                'required_unless:tipo,Efectivo',
                'nullable',
                'string',
                'max:255'
            ],
            'nombre_banco' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'string', 'max:255'],
            'saldo' => ['required', 'numeric'],
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'numero.required_unless' => 'El número de cuenta es requerido a menos que el tipo sea Efectivo.',
            'nombre_banco.required' => 'El nombre del banco es requerido.',
            'nombre_banco.max' => 'El nombre del banco no puede exceder 255 caracteres.',
            'tipo.required' => 'El tipo es requerido.',
            'saldo.required' => 'El saldo es requerido.',
            'saldo.numeric' => 'El saldo debe ser un número.',
            'id_empresa.required' => 'La empresa es requerida.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar nombre_banco
        if ($this->has('nombre_banco')) {
            $this->merge(['nombre_banco' => trim($this->nombre_banco)]);
        }

        // Convertir saldo a float
        if ($this->has('saldo')) {
            $this->merge(['saldo' => (float) $this->saldo]);
        }
    }
}

