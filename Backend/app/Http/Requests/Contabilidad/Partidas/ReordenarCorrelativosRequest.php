<?php

namespace App\Http\Requests\Contabilidad\Partidas;

use Illuminate\Foundation\Http\FormRequest;

class ReordenarCorrelativosRequest extends FormRequest
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
        // Si viene 'todos', no validar anio, mes, tipo
        if ($this->has('todos') && $this->todos) {
            return [
                'todos' => ['required', 'boolean'],
            ];
        }

        return [
            'anio' => ['required', 'integer', 'min:2020', 'max:2030'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'tipo' => ['required', 'string', 'in:Ingreso,Egreso,Diario,CxC,CxP,Cierre'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'anio.required' => 'El año es requerido.',
            'anio.integer' => 'El año debe ser un número entero.',
            'anio.min' => 'El año debe ser mayor o igual a 2020.',
            'anio.max' => 'El año debe ser menor o igual a 2030.',
            'mes.required' => 'El mes es requerido.',
            'mes.integer' => 'El mes debe ser un número entero.',
            'mes.min' => 'El mes debe ser mayor o igual a 1.',
            'mes.max' => 'El mes debe ser menor o igual a 12.',
            'tipo.required' => 'El tipo es requerido.',
            'tipo.in' => 'El tipo debe ser uno de: Ingreso, Egreso, Diario, CxC, CxP, Cierre.',
        ];
    }
}

