<?php

namespace App\Http\Requests\Ventas\Devoluciones;

use App\Support\FacturacionElectronica\CostaRica\ReferenciaComprobanteCr;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDevolucionRequest extends FormRequest
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
            'id' => 'sometimes|nullable|integer|exists:devoluciones_venta,id',
            'fecha' => 'required|date',
            'enable' => 'required|boolean',
            'observaciones' => 'required|string|max:500',
            'motivo_fe_cr' => [
                'nullable',
                'string',
                'max:40',
                Rule::in([
                    ReferenciaComprobanteCr::MOTIVO_DEVOLUCION,
                    ReferenciaComprobanteCr::MOTIVO_ANULA,
                    ReferenciaComprobanteCr::MOTIVO_EXONERACION_POSTERIOR,
                ]),
            ],
            'tipo' => 'required|string|in:devolucion,descuento_ajuste,anulacion_factura',
            'id_usuario' => 'required|integer|exists:users,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'La devolución no existe.',
            'fecha.required' => 'La fecha es obligatoria.',
            'fecha.date' => 'La fecha debe tener un formato válido.',
            'enable.required' => 'El estado enable es obligatorio.',
            'enable.boolean' => 'El estado enable debe ser verdadero o falso.',
            'observaciones.required' => 'Las observaciones son obligatorias.',
            'observaciones.max' => 'Las observaciones no pueden exceder 500 caracteres.',
            'motivo_fe_cr.in' => 'El motivo debe ser: devolución, anula el documento o exoneración posterior.',
            'tipo.required' => 'El tipo es obligatorio.',
            'tipo.in' => 'El tipo debe ser: devolucion, descuento_ajuste o anulacion_factura.',
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
            'fecha' => 'fecha',
            'enable' => 'estado',
            'observaciones' => 'observaciones',
            'tipo' => 'tipo',
            'id_usuario' => 'usuario',
        ];
    }
}

