<?php

namespace App\Http\Requests\Eventos;

use Illuminate\Foundation\Http\FormRequest;

class StoreDetalleEventoRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:detalles_evento,id'],
            'id_producto' => ['required', 'integer', 'exists:productos,id'],
            'cantidad' => ['required', 'numeric', 'min:0.01'],
            'id_evento' => ['required', 'integer', 'exists:eventos,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'El detalle seleccionado no existe.',
            'id_producto.required' => 'El producto es requerido.',
            'id_producto.exists' => 'El producto seleccionado no existe.',
            'cantidad.required' => 'La cantidad es requerida.',
            'cantidad.numeric' => 'La cantidad debe ser un número.',
            'cantidad.min' => 'La cantidad debe ser mayor a 0.',
            'id_evento.required' => 'El evento es requerido.',
            'id_evento.exists' => 'El evento seleccionado no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir cantidad a float
        if ($this->has('cantidad')) {
            $this->merge(['cantidad' => (float) $this->cantidad]);
        }
    }
}

