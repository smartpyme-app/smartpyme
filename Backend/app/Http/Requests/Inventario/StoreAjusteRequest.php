<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;

class StoreAjusteRequest extends FormRequest
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
            'id' => ['sometimes', 'nullable', 'integer', 'exists:ajustes,id'],
            'id_producto' => ['required', 'integer', 'exists:productos,id'],
            'id_bodega' => ['required', 'integer', 'exists:sucursal_bodegas,id'],
            'stock_actual' => ['required', 'numeric', 'min:0'],
            'stock_real' => ['required', 'numeric', 'min:0'],
            'ajuste' => ['required', 'numeric'],
            'concepto' => ['required', 'string', 'max:255'],
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
            'id_usuario' => ['required', 'integer', 'exists:users,id'],
            'lote_id'    => ['nullable' ,'numeric', 'exists:lotes,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id_producto.required' => 'El producto es requerido.',
            'id_producto.exists' => 'El producto seleccionado no existe.',
            'id_bodega.required' => 'La bodega es requerida.',
            'id_bodega.exists' => 'La bodega seleccionada no existe.',
            'stock_actual.required' => 'El stock actual es requerido.',
            'stock_actual.numeric' => 'El stock actual debe ser un número.',
            'stock_actual.min' => 'El stock actual no puede ser negativo.',
            'stock_real.required' => 'El stock real es requerido.',
            'stock_real.numeric' => 'El stock real debe ser un número.',
            'stock_real.min' => 'El stock real no puede ser negativo.',
            'ajuste.required' => 'El ajuste es requerido.',
            'ajuste.numeric' => 'El ajuste debe ser un número.',
            'concepto.required' => 'El concepto es requerido.',
            'concepto.max' => 'El concepto no puede exceder 255 caracteres.',
            'id_empresa.required' => 'La empresa es requerida.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'id_usuario.required' => 'El usuario es requerido.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir valores numéricos
        if ($this->has('stock_actual')) {
            $this->merge([
                'stock_actual' => (float) $this->stock_actual,
            ]);
        }

        if ($this->has('stock_real')) {
            $this->merge([
                'stock_real' => (float) $this->stock_real,
            ]);
        }

        if ($this->has('ajuste')) {
            $this->merge([
                'ajuste' => (float) $this->ajuste,
            ]);
        }
    }
}

