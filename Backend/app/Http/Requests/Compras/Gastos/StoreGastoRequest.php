<?php

namespace App\Http\Requests\Compras\Gastos;

use Illuminate\Foundation\Http\FormRequest;

class StoreGastoRequest extends FormRequest
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
            'id' => 'sometimes|nullable|integer|exists:gastos,id',
            'fecha' => 'required|date',
            'concepto' => 'sometimes|nullable|string|max:255',
            'tipo_documento' => 'required|string|max:255',
            'forma_pago' => 'required|string|max:255',
            'estado' => 'required|string|max:255',
            'total' => 'required|numeric|min:0',
            'id_categoria' => 'required|integer|exists:categorias_gastos,id',
            'id_proveedor' => 'required|integer|exists:proveedores,id',
            'id_usuario' => 'required|integer|exists:users,id',
            'id_sucursal' => 'required|integer|exists:sucursales,id',
            'id_empresa' => 'required|integer|exists:empresas,id',
            'otros_impuestos' => 'sometimes|nullable',
            'area_empresa' => 'sometimes|nullable|string|max:255',
            'id_area_empresa' => 'sometimes|nullable|integer',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'El gasto no existe.',
            'fecha.required' => 'La fecha es obligatoria.',
            'fecha.date' => 'La fecha debe tener un formato válido.',
            'concepto.max' => 'El concepto no puede exceder 255 caracteres.',
            'tipo_documento.required' => 'El tipo de documento es obligatorio.',
            'tipo_documento.max' => 'El tipo de documento no puede exceder 255 caracteres.',
            'forma_pago.required' => 'La forma de pago es obligatoria.',
            'forma_pago.max' => 'La forma de pago no puede exceder 255 caracteres.',
            'estado.required' => 'El estado es obligatorio.',
            'estado.max' => 'El estado no puede exceder 255 caracteres.',
            'total.required' => 'El total es obligatorio.',
            'total.numeric' => 'El total debe ser un número.',
            'total.min' => 'El total no puede ser negativo.',
            'id_categoria.required' => 'El campo categoria es obligatorio.',
            'id_categoria.exists' => 'La categoría seleccionada no existe.',
            'id_proveedor.required' => 'El campo proveedor es obligatorio.',
            'id_proveedor.exists' => 'El proveedor seleccionado no existe.',
            'id_usuario.required' => 'El campo usuario es obligatorio.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'id_sucursal.required' => 'La sucursal es obligatoria.',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
            'id_empresa.required' => 'El campo empresa es obligatorio.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'area_empresa.max' => 'El área de empresa no puede exceder 255 caracteres.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'fecha' => 'fecha',
            'concepto' => 'concepto',
            'tipo_documento' => 'tipo de documento',
            'forma_pago' => 'forma de pago',
            'estado' => 'estado',
            'total' => 'total',
            'id_categoria' => 'categoría',
            'id_proveedor' => 'proveedor',
            'id_usuario' => 'usuario',
            'id_sucursal' => 'sucursal',
            'id_empresa' => 'empresa',
            'area_empresa' => 'área de empresa',
            'id_area_empresa' => 'área de empresa',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Si otros_impuestos está vacío, establecerlo como null
        if ($this->has('otros_impuestos') && empty($this->otros_impuestos)) {
            $this->merge([
                'otros_impuestos' => null,
            ]);
        }
    }
}

