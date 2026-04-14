<?php

namespace App\Http\Requests\Compras\Retaceo;

use Illuminate\Foundation\Http\FormRequest;

class StoreRetaceoRequest extends FormRequest
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
            'id_compra' => 'required|integer|exists:compras,id',
            'fecha' => 'required|date',
            'total_gastos' => 'required|numeric|min:0',
            'gastos' => 'required|array|min:1',
            'gastos.*.id_gasto' => 'sometimes|nullable|integer|exists:egresos,id',
            'gastos.*.tipo_gasto' => 'sometimes|nullable|string|max:255',
            'gastos.*.monto' => 'required|numeric|min:0',
            'distribucion' => 'required|array|min:1',
            'distribucion.*.id_producto' => 'required|integer|exists:productos,id',
            'distribucion.*.id_detalle_compra' => 'required|integer|exists:detalles,id',
            'distribucion.*.cantidad' => 'required|numeric|min:0.01',
            'distribucion.*.costo_original' => 'required|numeric|min:0',
            'distribucion.*.valor_fob' => 'required|numeric|min:0',
            'distribucion.*.porcentaje_distribucion' => 'required|numeric|min:0|max:100',
            'distribucion.*.monto_transporte' => 'required|numeric|min:0',
            'distribucion.*.monto_seguro' => 'required|numeric|min:0',
            'distribucion.*.monto_dai' => 'required|numeric|min:0',
            'distribucion.*.monto_otros' => 'required|numeric|min:0',
            'distribucion.*.costo_landed' => 'required|numeric|min:0',
            'distribucion.*.costo_retaceado' => 'required|numeric|min:0',
            'distribucion.*.porcentaje_dai' => 'sometimes|nullable|numeric|min:0|max:100',
            'numero_duca' => 'sometimes|nullable|string|max:255',
            'tasa_dai' => 'sometimes|nullable|numeric|min:0|max:100',
            'incoterm' => 'sometimes|nullable|string|max:255',
            'observaciones' => 'sometimes|nullable|string|max:500',
            'total_retaceado' => 'sometimes|nullable|numeric|min:0',
            'id_empresa' => 'required|integer|exists:empresas,id',
            'id_sucursal' => 'required|integer|exists:sucursales,id',
            'id_usuario' => 'required|integer|exists:users,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id_compra.required' => 'La compra es obligatoria.',
            'id_compra.exists' => 'La compra seleccionada no existe.',
            'fecha.required' => 'La fecha es obligatoria.',
            'fecha.date' => 'La fecha debe tener un formato válido.',
            'total_gastos.required' => 'El total de gastos es obligatorio.',
            'total_gastos.numeric' => 'El total de gastos debe ser un número.',
            'total_gastos.min' => 'El total de gastos no puede ser negativo.',
            'gastos.required' => 'Los gastos son obligatorios.',
            'gastos.array' => 'Los gastos deben ser un array.',
            'gastos.min' => 'Debe haber al menos un gasto.',
            'gastos.*.monto.required' => 'El monto es obligatorio en cada gasto.',
            'gastos.*.monto.min' => 'El monto no puede ser negativo.',
            'distribucion.required' => 'La distribución es obligatoria.',
            'distribucion.array' => 'La distribución debe ser un array.',
            'distribucion.min' => 'Debe haber al menos un elemento en la distribución.',
            'distribucion.*.id_producto.required' => 'El producto es obligatorio en cada elemento de distribución.',
            'distribucion.*.id_producto.exists' => 'Uno de los productos seleccionados no existe.',
            'distribucion.*.id_detalle_compra.required' => 'El detalle de compra es obligatorio.',
            'distribucion.*.id_detalle_compra.exists' => 'Uno de los detalles de compra no existe.',
            'distribucion.*.cantidad.required' => 'La cantidad es obligatoria.',
            'distribucion.*.cantidad.min' => 'La cantidad debe ser mayor a 0.',
            'distribucion.*.costo_original.required' => 'El costo original es obligatorio.',
            'distribucion.*.costo_original.min' => 'El costo original no puede ser negativo.',
            'distribucion.*.valor_fob.required' => 'El valor FOB es obligatorio.',
            'distribucion.*.valor_fob.min' => 'El valor FOB no puede ser negativo.',
            'distribucion.*.porcentaje_distribucion.required' => 'El porcentaje de distribución es obligatorio.',
            'distribucion.*.porcentaje_distribucion.max' => 'El porcentaje de distribución no puede ser mayor a 100.',
            'distribucion.*.monto_transporte.required' => 'El monto de transporte es obligatorio.',
            'distribucion.*.monto_transporte.min' => 'El monto de transporte no puede ser negativo.',
            'distribucion.*.monto_seguro.required' => 'El monto de seguro es obligatorio.',
            'distribucion.*.monto_seguro.min' => 'El monto de seguro no puede ser negativo.',
            'distribucion.*.monto_dai.required' => 'El monto DAI es obligatorio.',
            'distribucion.*.monto_dai.min' => 'El monto DAI no puede ser negativo.',
            'distribucion.*.monto_otros.required' => 'El monto otros es obligatorio.',
            'distribucion.*.monto_otros.min' => 'El monto otros no puede ser negativo.',
            'distribucion.*.costo_landed.required' => 'El costo landed es obligatorio.',
            'distribucion.*.costo_landed.min' => 'El costo landed no puede ser negativo.',
            'distribucion.*.costo_retaceado.required' => 'El costo retaceado es obligatorio.',
            'distribucion.*.costo_retaceado.min' => 'El costo retaceado no puede ser negativo.',
            'tasa_dai.max' => 'La tasa DAI no puede ser mayor a 100.',
            'observaciones.max' => 'Las observaciones no pueden exceder 500 caracteres.',
            'id_empresa.required' => 'La empresa es obligatoria.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'id_sucursal.required' => 'La sucursal es obligatoria.',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
            'id_usuario.required' => 'El usuario es obligatorio.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'id_compra' => 'compra',
            'fecha' => 'fecha',
            'total_gastos' => 'total de gastos',
            'gastos' => 'gastos',
            'distribucion' => 'distribución',
            'numero_duca' => 'número DUA',
            'tasa_dai' => 'tasa DAI',
            'incoterm' => 'incoterm',
            'observaciones' => 'observaciones',
            'total_retaceado' => 'total retaceado',
            'id_empresa' => 'empresa',
            'id_sucursal' => 'sucursal',
            'id_usuario' => 'usuario',
        ];
    }
}

