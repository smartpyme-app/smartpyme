<?php

namespace App\Http\Requests\Admin\Canales;

use Illuminate\Foundation\Http\FormRequest;

class StoreCanalRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['required', 'string', 'max:255'],
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
            'id' => ['nullable', 'integer', 'exists:canales,id'],
            'enable' => ['nullable', 'boolean'],
            'cobra_propina' => ['nullable', 'boolean'],
            'envios' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es requerido.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'descripcion.required' => 'La descripción es requerida.',
            'descripcion.max' => 'La descripción no puede exceder 255 caracteres.',
            'id_empresa.required' => 'La empresa es requerida.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'id.exists' => 'El canal seleccionado no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar strings
        if ($this->has('nombre')) {
            $this->merge(['nombre' => trim($this->nombre)]);
        }

        if ($this->has('descripcion')) {
            $this->merge(['descripcion' => trim($this->descripcion)]);
        }
    }
}

