<?php

namespace App\Http\Requests\Planilla;

use Illuminate\Foundation\Http\FormRequest;

class IndexPlanillaRequest extends FormRequest
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
            'anio' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'mes' => ['nullable', 'integer', 'min:1', 'max:12'],
            'estado' => ['nullable', 'string', 'max:255'],
            'tipo_planilla' => ['nullable', 'string', 'max:255'],
            'buscador' => ['nullable', 'string', 'max:255'],
            'paginate' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'anio.integer' => 'El año debe ser un número entero.',
            'anio.min' => 'El año debe ser al menos 2000.',
            'anio.max' => 'El año no puede exceder 2100.',
            'mes.integer' => 'El mes debe ser un número entero.',
            'mes.min' => 'El mes debe ser al menos 1.',
            'mes.max' => 'El mes no puede exceder 12.',
            'estado.max' => 'El estado no puede exceder 255 caracteres.',
            'tipo_planilla.max' => 'El tipo de planilla no puede exceder 255 caracteres.',
            'buscador.max' => 'El buscador no puede exceder 255 caracteres.',
            'paginate.integer' => 'El número de registros por página debe ser un entero.',
            'paginate.min' => 'El número de registros por página debe ser al menos 1.',
            'paginate.max' => 'El número de registros por página no puede exceder 200.',
        ];
    }
}

