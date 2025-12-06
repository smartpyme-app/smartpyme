<?php

namespace App\Http\Requests\Compras\Cotizaciones;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrdenCompraRequest extends FormRequest
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
            'fecha' => ['required', 'date'],
            'id_usuario' => ['required', 'integer', 'exists:users,id'],
            'id_proveedor' => ['required', 'integer', 'exists:proveedores,id'],
            'id_bodega' => ['required', 'integer', 'exists:sucursal_bodegas,id'],
            'id' => ['nullable', 'integer', 'exists:orden_compras,id'],
            'id_authorization' => ['nullable', 'integer', 'exists:authorizations,id'],
            'estado' => ['nullable', 'string'],
            'detalles' => ['nullable', 'array'],
            'total' => ['nullable', 'numeric', 'min:0'],
            'sub_total' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string'],
            'referencia' => ['nullable', 'string'],
            'tipo_documento' => ['nullable', 'string'],
            'id_proyecto' => ['nullable', 'integer', 'exists:proyectos,id'],
            'cobrar_impuestos' => ['nullable', 'boolean'],
            'cobrar_percepcion' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha es requerida',
            'fecha.date' => 'La fecha debe ser una fecha válida.',
            'id_usuario.required' => 'El usuario es requerido',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'id_proveedor.required' => 'El proveedor es requerido',
            'id_proveedor.exists' => 'El proveedor seleccionado no existe.',
            'id_bodega.required' => 'La bodega es requerida',
            'id_bodega.exists' => 'La bodega seleccionada no existe.',
            'id.exists' => 'La orden de compra seleccionada no existe.',
            'id_authorization.exists' => 'La autorización seleccionada no existe.',
            'id_proyecto.exists' => 'El proyecto seleccionado no existe.',
            'total.numeric' => 'El total debe ser un número.',
            'total.min' => 'El total no puede ser negativo.',
            'sub_total.numeric' => 'El subtotal debe ser un número.',
            'sub_total.min' => 'El subtotal no puede ser negativo.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar strings
        if ($this->has('observaciones')) {
            $this->merge(['observaciones' => trim($this->observaciones)]);
        }

        if ($this->has('referencia')) {
            $this->merge(['referencia' => trim($this->referencia)]);
        }

        // Convertir valores numéricos
        if ($this->has('total')) {
            $this->merge(['total' => (float) $this->total]);
        }

        if ($this->has('sub_total')) {
            $this->merge(['sub_total' => (float) $this->sub_total]);
        }
    }
}

