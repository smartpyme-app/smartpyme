<?php

namespace App\Http\Requests\Planilla;

use Illuminate\Foundation\Http\FormRequest;

class ShowPlanillaRequest extends FormRequest
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
            'id' => ['required', 'integer', 'exists:planillas,id'],
            'buscador' => ['nullable', 'string', 'max:255'],
            'id_departamento' => ['nullable', 'integer', 'exists:departamentos_empresa,id'],
            'id_cargo' => ['nullable', 'integer', 'exists:cargos_de_empresa,id'],
            'paginate' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.required' => 'El ID de la planilla es requerido.',
            'id.integer' => 'El ID de la planilla debe ser un número entero.',
            'id.exists' => 'La planilla seleccionada no existe.',
            'buscador.max' => 'El buscador no puede exceder 255 caracteres.',
            'id_departamento.integer' => 'El ID del departamento debe ser un número entero.',
            'id_departamento.exists' => 'El departamento seleccionado no existe.',
            'id_cargo.integer' => 'El ID del cargo debe ser un número entero.',
            'id_cargo.exists' => 'El cargo seleccionado no existe.',
            'paginate.integer' => 'El número de registros por página debe ser un entero.',
            'paginate.min' => 'El número de registros por página debe ser al menos 1.',
            'paginate.max' => 'El número de registros por página no puede exceder 200.',
        ];
    }
}

