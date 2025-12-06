<?php

namespace App\Http\Requests\Admin\UsuariosMeta;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmpleadoMetaRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:empleados_meta,id'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'ano' => ['required', 'integer', 'min:2000', 'max:2100'],
            'meta' => ['required', 'numeric', 'min:0'],
            'usuario_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'La meta seleccionada no existe.',
            'mes.required' => 'El mes es requerido.',
            'mes.integer' => 'El mes debe ser un número entero.',
            'mes.min' => 'El mes debe ser al menos 1.',
            'mes.max' => 'El mes no puede ser mayor a 12.',
            'ano.required' => 'El año es requerido.',
            'ano.integer' => 'El año debe ser un número entero.',
            'ano.min' => 'El año debe ser al menos 2000.',
            'ano.max' => 'El año no puede ser mayor a 2100.',
            'meta.required' => 'La meta es requerida.',
            'meta.numeric' => 'La meta debe ser un número.',
            'meta.min' => 'La meta debe ser mayor o igual a 0.',
            'usuario_id.required' => 'El usuario es requerido.',
            'usuario_id.exists' => 'El usuario seleccionado no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir valores a enteros
        if ($this->has('mes')) {
            $this->merge(['mes' => (int) $this->mes]);
        }

        if ($this->has('ano')) {
            $this->merge(['ano' => (int) $this->ano]);
        }

        // Convertir meta a float
        if ($this->has('meta')) {
            $this->merge(['meta' => (float) $this->meta]);
        }
    }
}

