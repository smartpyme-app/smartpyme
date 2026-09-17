<?php

namespace App\Http\Requests\Contabilidad;

use Illuminate\Foundation\Http\FormRequest;

class StoreActivoConfiguracionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'frecuencia' => ['nullable', 'string', 'in:mensual'],
            'dia_corte' => ['required', 'integer', 'min:1', 'max:28'],
            'redondeo_decimales' => ['required', 'integer', 'min:0', 'max:4'],
        ];
    }
}
