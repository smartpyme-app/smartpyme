<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;

class StoreBodegaRequest extends FormRequest
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
            'id' => 'sometimes|nullable|integer|exists:sucursal_bodegas,id',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'sometimes|nullable|string|max:255',
            'id_sucursal' => 'required|integer|exists:sucursales,id',
            'id_empresa' => 'required|integer|exists:empresas,id',
            'activo' => 'sometimes|nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'La bodega no existe.',
            'nombre.required' => 'El nombre de la bodega es obligatorio.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'descripcion.max' => 'La descripción no puede exceder 255 caracteres.',
            'id_sucursal.required' => 'La sucursal es obligatoria.',
            'id_sucursal.integer' => 'El ID de la sucursal debe ser un número entero.',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
            'id_empresa.required' => 'La empresa es obligatoria.',
            'id_empresa.integer' => 'El ID de la empresa debe ser un número entero.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'activo.boolean' => 'El estado activo debe ser verdadero o falso.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre de la bodega',
            'descripcion' => 'descripción',
            'id_sucursal' => 'sucursal',
            'id_empresa' => 'empresa',
            'activo' => 'estado activo',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar nombre
        if ($this->has('nombre')) {
            $this->merge([
                'nombre' => trim($this->nombre),
            ]);
        }

        // Sanitizar descripción
        if ($this->has('descripcion') && $this->descripcion) {
            $this->merge([
                'descripcion' => trim($this->descripcion),
            ]);
        }

        // Convertir activo a boolean si viene como string
        if ($this->has('activo')) {
            $this->merge([
                'activo' => filter_var($this->activo, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $this->activo,
            ]);
        }
    }
}

