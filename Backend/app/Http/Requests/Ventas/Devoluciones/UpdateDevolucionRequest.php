<?php

namespace App\Http\Requests\Ventas\Devoluciones;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDevolucionRequest extends FormRequest
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
            'id' => 'required|integer|exists:devoluciones_venta,id',
            'fecha' => 'required|date',
            'id_documento' => 'nullable|integer|exists:documentos,id',
            'correlativo' => 'nullable|string|max:255',
            'id_usuario' => 'required|integer|exists:users,id',
            'observaciones' => 'required|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.required' => 'El ID de la devolución es obligatorio.',
            'id.exists' => 'La devolución no existe.',
            'fecha.required' => 'La fecha es requerida.',
            'fecha.date' => 'La fecha debe tener un formato válido.',
            'id_documento.exists' => 'El documento seleccionado no existe.',
            'correlativo.max' => 'El correlativo no puede exceder 255 caracteres.',
            'id_usuario.required' => 'El usuario es requerido.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'observaciones.required' => 'Las observaciones son requeridas.',
            'observaciones.max' => 'Las observaciones no pueden exceder 500 caracteres.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'id' => 'devolución',
            'fecha' => 'fecha',
            'id_documento' => 'documento',
            'correlativo' => 'correlativo',
            'id_usuario' => 'usuario',
            'observaciones' => 'observaciones',
        ];
    }
}

