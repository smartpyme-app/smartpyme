<?php

namespace App\Http\Requests\Ventas\OrdenProduccion;

use Illuminate\Foundation\Http\FormRequest;

class CambiarEstadoOrdenRequest extends FormRequest
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
            'id' => 'required|integer|exists:orden_produccion,id',
            'estado' => 'required|string|max:255',
            'comentarios' => 'sometimes|nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.required' => 'El ID de la orden es obligatorio.',
            'id.exists' => 'La orden no existe.',
            'estado.required' => 'El estado es obligatorio.',
            'estado.max' => 'El estado no puede exceder 255 caracteres.',
            'comentarios.max' => 'Los comentarios no pueden exceder 500 caracteres.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'id' => 'orden',
            'estado' => 'estado',
            'comentarios' => 'comentarios',
        ];
    }
}

