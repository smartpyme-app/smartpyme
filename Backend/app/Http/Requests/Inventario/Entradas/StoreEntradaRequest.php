<?php

namespace App\Http\Requests\Inventario\Entradas;

use Illuminate\Foundation\Http\FormRequest;

class StoreEntradaRequest extends FormRequest
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
            'id' => ['sometimes', 'nullable', 'integer', 'exists:inventario_entradas,id'],
            'fecha' => ['required', 'date'],
            'id_bodega' => ['required', 'integer', 'exists:sucursal_bodegas,id'],
            'concepto' => ['required', 'string', 'max:255'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id_producto' => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0'],
            'detalles.*.costo' => ['nullable', 'numeric', 'min:0'],
            'id_usuario' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha es requerida.',
            'fecha.date' => 'La fecha debe ser una fecha válida.',
            'id_bodega.required' => 'La bodega es requerida.',
            'id_bodega.exists' => 'La bodega seleccionada no existe.',
            'concepto.required' => 'El concepto es requerido.',
            'concepto.max' => 'El concepto no puede exceder 255 caracteres.',
            'detalles.required' => 'Los detalles son requeridos.',
            'detalles.array' => 'Los detalles deben ser un arreglo.',
            'detalles.min' => 'Debe haber al menos un detalle.',
            'detalles.*.id_producto.required' => 'El producto es requerido en cada detalle.',
            'detalles.*.id_producto.exists' => 'El producto seleccionado no existe.',
            'detalles.*.cantidad.required' => 'La cantidad es requerida en cada detalle.',
            'detalles.*.cantidad.numeric' => 'La cantidad debe ser un número.',
            'detalles.*.cantidad.min' => 'La cantidad no puede ser negativa.',
            'id_usuario.required' => 'El usuario es requerido.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir valores numéricos en detalles
        if ($this->has('detalles') && is_array($this->detalles)) {
            $detalles = [];
            foreach ($this->detalles as $detalle) {
                if (isset($detalle['cantidad'])) {
                    $detalle['cantidad'] = (float) $detalle['cantidad'];
                }
                if (isset($detalle['costo'])) {
                    $detalle['costo'] = (float) $detalle['costo'];
                }
                $detalles[] = $detalle;
            }
            $this->merge(['detalles' => $detalles]);
        }
    }
}

