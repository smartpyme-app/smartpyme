<?php

namespace App\Http\Requests\Admin\Sucursales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSucursalRequest extends FormRequest
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
            'id' => 'sometimes|nullable|integer|exists:sucursales,id',
            'nombre' => 'required|string|max:255',
            'id_empresa' => 'required|integer|exists:empresas,id',
            'telefono' => 'sometimes|nullable|string|max:255',
            'correo' => 'sometimes|nullable|email|max:255',
            'direccion' => 'sometimes|nullable|string|max:500',
            'municipio' => 'sometimes|nullable|string|max:255',
            'departamento' => 'sometimes|nullable|string|max:255',
            'tipo_establecimiento' => 'sometimes|nullable|string|max:255',
            'cod_estable_mh' => 'sometimes|nullable|string|max:255',
            'codigo_punto_venta' => 'sometimes|nullable|string|max:255',
            'activo' => 'sometimes|nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'La sucursal no existe.',
            'nombre.required' => 'El nombre de la sucursal es obligatorio.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'id_empresa.required' => 'La empresa es obligatoria.',
            'id_empresa.integer' => 'El ID de la empresa debe ser un número entero.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'telefono.max' => 'El teléfono no puede exceder 255 caracteres.',
            'correo.email' => 'El correo electrónico debe ser una dirección válida.',
            'correo.max' => 'El correo electrónico no puede exceder 255 caracteres.',
            'direccion.max' => 'La dirección no puede exceder 500 caracteres.',
            'municipio.max' => 'El municipio no puede exceder 255 caracteres.',
            'departamento.max' => 'El departamento no puede exceder 255 caracteres.',
            'tipo_establecimiento.max' => 'El tipo de establecimiento no puede exceder 255 caracteres.',
            'cod_estable_mh.max' => 'El código de establecimiento MH no puede exceder 255 caracteres.',
            'codigo_punto_venta.max' => 'El código de punto de venta no puede exceder 255 caracteres.',
            'activo.boolean' => 'El estado activo debe ser verdadero o falso.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre de la sucursal',
            'id_empresa' => 'empresa',
            'telefono' => 'teléfono',
            'correo' => 'correo electrónico',
            'direccion' => 'dirección',
            'municipio' => 'municipio',
            'departamento' => 'departamento',
            'tipo_establecimiento' => 'tipo de establecimiento',
            'cod_estable_mh' => 'código de establecimiento MH',
            'codigo_punto_venta' => 'código de punto de venta',
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

        // Sanitizar correo
        if ($this->has('correo') && $this->correo) {
            $this->merge([
                'correo' => strtolower(trim($this->correo)),
            ]);
        }

        // Sanitizar teléfono (remover caracteres no numéricos)
        if ($this->has('telefono') && $this->telefono) {
            $this->merge([
                'telefono' => preg_replace('/[^0-9]/', '', $this->telefono),
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

