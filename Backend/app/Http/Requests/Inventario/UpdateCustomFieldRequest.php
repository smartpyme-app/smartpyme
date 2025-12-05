<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomFieldRequest extends FormRequest
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
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'field_type' => ['required', 'string', 'in:select,text,number'],
            'is_required' => ['required', 'boolean'],
        ];

        // Si el tipo es 'select', los valores son requeridos
        if ($this->field_type === 'select') {
            $rules['values'] = ['required', 'array', 'min:1'];
            $rules['values.*.value'] = ['required', 'string', 'max:255'];
            $rules['values.*.id'] = ['sometimes', 'nullable', 'integer', 'exists:custom_field_values,id'];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es requerido.',
            'name.max' => 'El nombre no puede exceder 255 caracteres.',
            'field_type.required' => 'El tipo de campo es requerido.',
            'field_type.in' => 'El tipo de campo debe ser: select, text o number.',
            'is_required.required' => 'El campo is_required es requerido.',
            'is_required.boolean' => 'El campo is_required debe ser un booleano.',
            'values.required' => 'Los valores son requeridos para campos de tipo select.',
            'values.array' => 'Los valores deben ser un arreglo.',
            'values.min' => 'Debe haber al menos un valor para campos de tipo select.',
            'values.*.value.required' => 'Cada valor es requerido.',
            'values.*.value.max' => 'Cada valor no puede exceder 255 caracteres.',
            'values.*.id.exists' => 'El ID del valor no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar name
        if ($this->has('name')) {
            $this->merge([
                'name' => trim($this->name),
            ]);
        }

        // Sanitizar valores si existen
        if ($this->has('values') && is_array($this->values)) {
            $values = [];
            foreach ($this->values as $value) {
                if (isset($value['value'])) {
                    $value['value'] = trim($value['value']);
                }
                $values[] = $value;
            }
            $this->merge(['values' => $values]);
        }
    }
}

