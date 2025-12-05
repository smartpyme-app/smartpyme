<?php

namespace App\Http\Requests\Admin\Cajas;

use Illuminate\Foundation\Http\FormRequest;

class StoreCajaRequest extends FormRequest
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
            'tipo' => ['required', 'string', 'max:255'],
            'sucursal_id' => ['required', 'integer', 'exists:sucursales,id'],
            'id' => ['nullable', 'integer', 'exists:cajas,id'],
            'descripcion' => ['nullable', 'string'],
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
            'tipo.required' => 'El tipo es requerido.',
            'tipo.max' => 'El tipo no puede exceder 255 caracteres.',
            'sucursal_id.required' => 'La sucursal es requerida.',
            'sucursal_id.exists' => 'La sucursal seleccionada no existe.',
            'id.exists' => 'La caja seleccionada no existe.',
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

        if ($this->has('tipo')) {
            $this->merge(['tipo' => trim($this->tipo)]);
        }

        if ($this->has('descripcion')) {
            $this->merge(['descripcion' => trim($this->descripcion)]);
        }
    }
}

