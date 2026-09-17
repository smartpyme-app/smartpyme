<?php

namespace App\Http\Requests\Contabilidad;

use Illuminate\Foundation\Http\FormRequest;

class StoreActivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'exists:empresa_activos,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'fecha_compra' => ['required', 'date'],
            'fecha_retiro' => ['nullable', 'date'],
            'estado' => ['nullable', 'string', 'max:32'],
            'id_categoria' => ['required', 'integer', 'exists:empresa_activos_categorias,id'],
            'numero_de_serie' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'ubicacion' => ['nullable', 'string', 'max:255'],
            'vida_util' => ['nullable', 'numeric', 'min:0'],
            'valor_compra' => ['required', 'numeric', 'min:0'],
            'valor_residual' => ['nullable', 'numeric', 'min:0'],
            'es_usado' => ['nullable', 'boolean'],
            'porcentaje_base_usado' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fecha_inicio_depreciacion' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
            'id_responsable' => ['nullable', 'integer', 'exists:users,id'],
            'id_sucursal' => ['nullable', 'integer', 'exists:sucursales,id'],
            'id_egreso' => ['nullable', 'integer', 'exists:egresos,id'],
            'id_compra_detalle' => ['nullable', 'integer', 'exists:detalles_compra,id'],
        ];
    }
}
