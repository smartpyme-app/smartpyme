<?php

namespace App\Http\Requests\Compras\Retaceo;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarEstadoRetaceoRequest extends FormRequest
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
            'id' => 'required|integer|exists:retaceos,id',
            'estado' => 'required|string|in:Pendiente,Aplicado,Anulado',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.required' => 'El ID del retaceo es obligatorio.',
            'id.exists' => 'El retaceo no existe.',
            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'El estado debe ser: Pendiente, Aplicado o Anulado.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'id' => 'retaceo',
            'estado' => 'estado',
        ];
    }
}

