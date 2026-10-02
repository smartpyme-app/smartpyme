<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;

class EstadoColaAnalisisVentasMensualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_empresa.required' => 'El ID de empresa es obligatorio.',
            'id_empresa.integer' => 'El ID de empresa debe ser un número entero.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
        ];
    }
}
