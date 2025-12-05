<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServicioRequest extends FormRequest
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
            'id' => ['sometimes', 'nullable', 'integer', 'exists:productos,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'codigo' => ['nullable', 'string', 'max:255'],
            'precio' => ['required', 'numeric', 'min:0'],
            'costo' => ['required', 'numeric', 'min:0'],
            'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
            'empresa_id' => ['required', 'integer', 'exists:empresas,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es requerido.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'codigo.max' => 'El código no puede exceder 255 caracteres.',
            'precio.required' => 'El precio es requerido.',
            'precio.numeric' => 'El precio debe ser un número.',
            'precio.min' => 'El precio no puede ser negativo.',
            'costo.required' => 'El costo es requerido.',
            'costo.numeric' => 'El costo debe ser un número.',
            'costo.min' => 'El costo no puede ser negativo.',
            'categoria_id.required' => 'La categoría es requerida.',
            'categoria_id.exists' => 'La categoría seleccionada no existe.',
            'empresa_id.required' => 'La empresa es requerida.',
            'empresa_id.exists' => 'La empresa seleccionada no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir codigo vacío a null
        if ($this->has('codigo') && empty($this->codigo)) {
            $this->merge(['codigo' => null]);
        }

        // Sanitizar nombre
        if ($this->has('nombre')) {
            $this->merge([
                'nombre' => trim($this->nombre),
            ]);
        }

        // Convertir valores numéricos
        if ($this->has('precio')) {
            $this->merge([
                'precio' => (float) $this->precio,
            ]);
        }

        if ($this->has('costo')) {
            $this->merge([
                'costo' => (float) $this->costo,
            ]);
        }
    }
}

