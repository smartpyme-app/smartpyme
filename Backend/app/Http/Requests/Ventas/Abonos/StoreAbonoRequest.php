<?php

namespace App\Http\Requests\Ventas\Abonos;

use Illuminate\Foundation\Http\FormRequest;

class StoreAbonoRequest extends FormRequest
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
            'id' => 'sometimes|nullable|integer|exists:abonos_ventas,id',
            'fecha' => 'required|date',
            'concepto' => 'required|string|max:255',
            'nombre_de' => 'required|string|max:255',
            'estado' => 'required|string|max:255',
            'forma_pago' => 'required|string|max:255',
            'detalle_banco' => 'required_unless:forma_pago,Efectivo|nullable|string|max:255',
            'total' => 'required|numeric|min:0',
            'id_venta' => 'required|integer|exists:ventas,id',
            'id_usuario' => 'required|integer|exists:users,id',
            'id_sucursal' => 'required|integer|exists:sucursales,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'El abono no existe.',
            'fecha.required' => 'La fecha es obligatoria.',
            'fecha.date' => 'La fecha debe tener un formato válido.',
            'concepto.required' => 'El concepto es obligatorio.',
            'concepto.max' => 'El concepto no puede exceder 255 caracteres.',
            'nombre_de.required' => 'El campo "nombre de" es obligatorio.',
            'nombre_de.max' => 'El campo "nombre de" no puede exceder 255 caracteres.',
            'estado.required' => 'El estado es obligatorio.',
            'estado.max' => 'El estado no puede exceder 255 caracteres.',
            'forma_pago.required' => 'La forma de pago es obligatoria.',
            'forma_pago.max' => 'La forma de pago no puede exceder 255 caracteres.',
            'detalle_banco.required_unless' => 'El detalle bancario es obligatorio cuando la forma de pago no es Efectivo.',
            'detalle_banco.max' => 'El detalle bancario no puede exceder 255 caracteres.',
            'total.required' => 'El total es obligatorio.',
            'total.numeric' => 'El total debe ser un número.',
            'total.min' => 'El total no puede ser negativo.',
            'id_venta.required' => 'La venta es obligatoria.',
            'id_venta.exists' => 'La venta seleccionada no existe.',
            'id_usuario.required' => 'El usuario es obligatorio.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'id_sucursal.required' => 'La sucursal es obligatoria.',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
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
            'nombre_de' => 'nombre de',
            'estado' => 'estado',
            'forma_pago' => 'forma de pago',
            'detalle_banco' => 'detalle bancario',
            'total' => 'total',
            'id_venta' => 'venta',
            'id_usuario' => 'usuario',
            'id_sucursal' => 'sucursal',
        ];
    }
}

