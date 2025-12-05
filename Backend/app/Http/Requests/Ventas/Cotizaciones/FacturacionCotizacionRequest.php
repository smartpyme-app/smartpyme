<?php

namespace App\Http\Requests\Ventas\Cotizaciones;

use Illuminate\Foundation\Http\FormRequest;

class FacturacionCotizacionRequest extends FormRequest
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
            'id' => 'sometimes|nullable|integer|exists:ventas,id',
            'fecha' => 'required|date',
            'estado' => 'required|string|max:255',
            'mesa' => 'required|numeric|min:0',
            'cliente' => 'required|array',
            'cliente.id' => 'sometimes|nullable|integer|exists:clientes,id',
            'cliente.nombre' => 'sometimes|nullable|string|max:255',
            'detalles' => 'required|array|min:1',
            'detalles.*.id' => 'sometimes|nullable|integer|exists:detalles,id',
            'detalles.*.id_producto' => 'required|integer|exists:productos,id',
            'detalles.*.cantidad' => 'required|numeric|min:0.01',
            'detalles.*.precio' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'usuario_id' => 'required|integer|exists:users,id',
            'sucursal_id' => 'required|integer|exists:sucursales,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha es obligatoria.',
            'fecha.date' => 'La fecha debe tener un formato válido.',
            'estado.required' => 'El estado es obligatorio.',
            'estado.max' => 'El estado no puede exceder 255 caracteres.',
            'mesa.required' => 'El número de mesa es obligatorio.',
            'mesa.numeric' => 'El número de mesa debe ser un número.',
            'mesa.min' => 'El número de mesa no puede ser negativo.',
            'cliente.required' => 'El cliente es obligatorio.',
            'cliente.array' => 'El cliente debe ser un objeto.',
            'cliente.id.exists' => 'El cliente seleccionado no existe.',
            'detalles.required' => 'Los detalles son obligatorios.',
            'detalles.array' => 'Los detalles deben ser un array.',
            'detalles.min' => 'Debe haber al menos un detalle.',
            'detalles.*.id_producto.required' => 'El producto es obligatorio en cada detalle.',
            'detalles.*.id_producto.exists' => 'Uno de los productos seleccionados no existe.',
            'detalles.*.cantidad.required' => 'La cantidad es obligatoria en cada detalle.',
            'detalles.*.cantidad.min' => 'La cantidad debe ser mayor a 0.',
            'detalles.*.precio.required' => 'El precio es obligatorio en cada detalle.',
            'detalles.*.precio.min' => 'El precio no puede ser negativo.',
            'total.required' => 'El total es obligatorio.',
            'total.numeric' => 'El total debe ser un número.',
            'total.min' => 'El total no puede ser negativo.',
            'usuario_id.required' => 'El usuario es obligatorio.',
            'usuario_id.exists' => 'El usuario seleccionado no existe.',
            'sucursal_id.required' => 'La sucursal es obligatoria.',
            'sucursal_id.exists' => 'La sucursal seleccionada no existe.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'fecha' => 'fecha',
            'estado' => 'estado',
            'mesa' => 'mesa',
            'cliente' => 'cliente',
            'detalles' => 'detalles',
            'total' => 'total',
            'usuario_id' => 'usuario',
            'sucursal_id' => 'sucursal',
        ];
    }
}

