<?php

namespace App\Http\Requests\Contabilidad;

use Illuminate\Foundation\Http\FormRequest;

class StorePresupuestoRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:presupuestos,id'],
            'titulo' => ['required', 'string', 'max:255'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'ingresos' => ['required', 'numeric', 'min:0'],
            'egresos' => ['required', 'numeric', 'min:0'],
            'compras' => ['required', 'numeric', 'min:0'],
            'utilidad' => ['required', 'numeric'],
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'titulo.required' => 'El título es requerido.',
            'titulo.max' => 'El título no puede exceder 255 caracteres.',
            'fecha_inicio.required' => 'La fecha de inicio es requerida.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_fin.required' => 'La fecha de fin es requerida.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'ingresos.required' => 'Los ingresos son requeridos.',
            'ingresos.numeric' => 'Los ingresos deben ser un número.',
            'ingresos.min' => 'Los ingresos no pueden ser negativos.',
            'egresos.required' => 'Los egresos son requeridos.',
            'egresos.numeric' => 'Los egresos deben ser un número.',
            'egresos.min' => 'Los egresos no pueden ser negativos.',
            'compras.required' => 'Las compras son requeridas.',
            'compras.numeric' => 'Las compras deben ser un número.',
            'compras.min' => 'Las compras no pueden ser negativas.',
            'utilidad.required' => 'La utilidad es requerida.',
            'utilidad.numeric' => 'La utilidad debe ser un número.',
            'id_empresa.required' => 'La empresa es requerida.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'id.exists' => 'El presupuesto seleccionado no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir valores numéricos
        $numericFields = ['ingresos', 'egresos', 'compras', 'utilidad'];
        
        foreach ($numericFields as $field) {
            if ($this->has($field)) {
                $this->merge([$field => (float) $this->$field]);
            }
        }

        // Limpiar strings
        if ($this->has('titulo')) {
            $this->merge(['titulo' => trim($this->titulo)]);
        }
    }
}

