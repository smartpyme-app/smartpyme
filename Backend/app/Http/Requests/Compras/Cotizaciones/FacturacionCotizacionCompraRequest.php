<?php

namespace App\Http\Requests\Compras\Cotizaciones;

use Illuminate\Foundation\Http\FormRequest;

class FacturacionCotizacionCompraRequest extends FormRequest
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
            'estado' => ['required', 'string', 'max:255'],
            'mesa' => ['required', 'numeric'],
            'proveedor' => ['required', 'array'],
            'proveedor.id' => ['nullable', 'integer', 'exists:clientes,id'],
            'proveedor.nombre' => ['nullable', 'string'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id' => ['nullable', 'integer', 'exists:detalles_compra,id'],
            'detalles.*.id_producto' => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.costo' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
            'usuario_id' => ['required', 'integer', 'exists:users,id'],
            'sucursal_id' => ['required', 'integer', 'exists:sucursales,id'],
            'id' => ['nullable', 'integer', 'exists:compras,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha es requerida.',
            'fecha.date' => 'La fecha debe ser una fecha válida.',
            'estado.required' => 'El estado es requerido.',
            'estado.max' => 'El estado no puede exceder 255 caracteres.',
            'mesa.required' => 'La mesa es requerida.',
            'mesa.numeric' => 'La mesa debe ser un número.',
            'proveedor.required' => 'El proveedor es requerido.',
            'proveedor.array' => 'El proveedor debe ser un objeto.',
            'proveedor.id.exists' => 'El proveedor seleccionado no existe.',
            'detalles.required' => 'Los detalles son requeridos.',
            'detalles.array' => 'Los detalles deben ser un arreglo.',
            'detalles.min' => 'Debe haber al menos un detalle.',
            'detalles.*.id.exists' => 'Uno o más detalles no existen.',
            'detalles.*.id_producto.required' => 'El producto es requerido para cada detalle.',
            'detalles.*.id_producto.exists' => 'Uno o más productos no existen.',
            'detalles.*.cantidad.required' => 'La cantidad es requerida para cada detalle.',
            'detalles.*.cantidad.min' => 'La cantidad debe ser mayor a 0.',
            'detalles.*.costo.required' => 'El costo es requerido para cada detalle.',
            'detalles.*.costo.min' => 'El costo no puede ser negativo.',
            'total.required' => 'El total es requerido.',
            'total.numeric' => 'El total debe ser un número.',
            'total.min' => 'El total no puede ser negativo.',
            'usuario_id.required' => 'El usuario es requerido.',
            'usuario_id.exists' => 'El usuario seleccionado no existe.',
            'sucursal_id.required' => 'La sucursal es requerida.',
            'sucursal_id.exists' => 'La sucursal seleccionada no existe.',
            'id.exists' => 'La cotización seleccionada no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir valores numéricos
        if ($this->has('total')) {
            $this->merge(['total' => (float) $this->total]);
        }
    }
}

