<?php

namespace App\Http\Requests\Compras\Retaceo;

use Illuminate\Foundation\Http\FormRequest;

class CalcularDistribucionRetaceoRequest extends FormRequest
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
            'gastos' => 'required|array',
            'gastos.transporte' => 'sometimes|nullable|numeric|min:0',
            'gastos.seguro' => 'sometimes|nullable|numeric|min:0',
            'gastos.dai' => 'sometimes|nullable|numeric|min:0',
            'gastos.otros' => 'sometimes|nullable|numeric|min:0',
            'detalles' => 'required|array|min:1',
            'detalles.*.id' => 'sometimes|nullable|integer',
            'detalles.*.id_producto' => 'required|integer|exists:productos,id',
            'detalles.*.cantidad' => 'required|numeric|min:0.01',
            'detalles.*.costo_original' => 'required|numeric|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'gastos.required' => 'Los gastos son obligatorios.',
            'gastos.array' => 'Los gastos deben ser un array.',
            'gastos.transporte.numeric' => 'El gasto de transporte debe ser un número.',
            'gastos.transporte.min' => 'El gasto de transporte no puede ser negativo.',
            'gastos.seguro.numeric' => 'El gasto de seguro debe ser un número.',
            'gastos.seguro.min' => 'El gasto de seguro no puede ser negativo.',
            'gastos.dai.numeric' => 'El gasto DAI debe ser un número.',
            'gastos.dai.min' => 'El gasto DAI no puede ser negativo.',
            'gastos.otros.numeric' => 'El gasto otros debe ser un número.',
            'gastos.otros.min' => 'El gasto otros no puede ser negativo.',
            'detalles.required' => 'Los detalles son obligatorios.',
            'detalles.array' => 'Los detalles deben ser un array.',
            'detalles.min' => 'Debe haber al menos un detalle.',
            'detalles.*.id_producto.required' => 'El producto es obligatorio en cada detalle.',
            'detalles.*.id_producto.exists' => 'Uno de los productos seleccionados no existe.',
            'detalles.*.cantidad.required' => 'La cantidad es obligatoria en cada detalle.',
            'detalles.*.cantidad.min' => 'La cantidad debe ser mayor a 0.',
            'detalles.*.costo_original.required' => 'El costo original es obligatorio en cada detalle.',
            'detalles.*.costo_original.min' => 'El costo original no puede ser negativo.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'gastos' => 'gastos',
            'detalles' => 'detalles',
        ];
    }
}

