<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Inventario\Sucursal;

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
            'id' => 'sometimes|nullable|integer',
            'producto_id' => [
                'required',
                'integer',
                'exists:productos,id',
            ],
            'activo' => 'required|boolean',
            'sucursal_id' => [
                'required',
                'integer',
                'exists:sucursales,id',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.integer' => 'El ID debe ser un número entero.',
            'producto_id.required' => 'El ID del producto es obligatorio.',
            'producto_id.integer' => 'El ID del producto debe ser un número entero.',
            'producto_id.exists' => 'El producto seleccionado no existe.',
            'activo.required' => 'El estado activo es obligatorio.',
            'activo.boolean' => 'El estado activo debe ser verdadero o falso.',
            'sucursal_id.required' => 'El ID de la sucursal es obligatorio.',
            'sucursal_id.integer' => 'El ID de la sucursal debe ser un número entero.',
            'sucursal_id.exists' => 'La sucursal seleccionada no existe.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'producto_id' => 'producto',
            'activo' => 'estado activo',
            'sucursal_id' => 'sucursal',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Solo validar duplicados si no es una actualización
            if (!$this->input('id')) {
                $existe = Sucursal::where('sucursal_id', $this->sucursal_id)
                    ->where('producto_id', $this->producto_id)
                    ->first();

                if ($existe) {
                    $validator->errors()->add('sucursal_id', 'Esta sucursal ya ha sido configurada para este producto.');
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir activo a boolean
        if ($this->has('activo')) {
            $this->merge([
                'activo' => filter_var($this->activo, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}

