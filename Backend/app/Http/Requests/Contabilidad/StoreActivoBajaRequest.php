<?php

namespace App\Http\Requests\Contabilidad;

use Illuminate\Foundation\Http\FormRequest;

class StoreActivoBajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', 'in:desecho,venta,transferencia'],
            'fecha' => ['required', 'date'],
            'motivo' => ['nullable', 'string', 'max:500'],
            'monto_venta' => ['nullable', 'numeric', 'min:0', 'required_if:tipo,venta'],
            'id_sucursal' => ['nullable', 'integer', 'exists:sucursales,id'],
            'id_responsable' => ['nullable', 'integer', 'exists:users,id'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
        ];
    }
}
