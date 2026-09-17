<?php

namespace App\Http\Requests\Contabilidad;

use Illuminate\Foundation\Http\FormRequest;

class StoreActivoCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'exists:empresa_activos_categorias,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'metodo_depreciacion' => ['nullable', 'string', 'max:32'],
            'porcentaje_anual' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'vida_util_anios' => ['nullable', 'numeric', 'min:0'],
            'valor_residual_default' => ['nullable', 'numeric', 'min:0'],
            'permite_bien_usado' => ['nullable', 'boolean'],
        ];
    }
}
