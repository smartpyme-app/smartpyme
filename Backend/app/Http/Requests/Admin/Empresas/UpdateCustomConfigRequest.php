<?php

namespace App\Http\Requests\Admin\Empresas;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomConfigRequest extends FormRequest
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
            'section' => 'required|string|in:columnas,modulos,configuraciones,campos_personalizados',
            'key' => 'required|string',
            'value' => 'required',
        ];

        // Validación condicional para ticket_en_pdf
        if ($this->input('section') === 'configuraciones' && $this->input('key') === 'ticket_en_pdf') {
            $rules['value'] = 'required|boolean';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'section.required' => 'La sección es obligatoria.',
            'section.in' => 'La sección debe ser una de: columnas, modulos, configuraciones, campos_personalizados.',
            'key.required' => 'La clave es obligatoria.',
            'key.string' => 'La clave debe ser una cadena de texto.',
            'value.required' => 'El valor es obligatorio.',
            'value.boolean' => 'El valor debe ser verdadero o falso.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'section' => 'sección',
            'key' => 'clave',
            'value' => 'valor',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir value a boolean si es necesario
        if ($this->input('section') === 'configuraciones' && $this->input('key') === 'ticket_en_pdf') {
            $this->merge([
                'value' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validación adicional para asegurar que value sea boolean cuando corresponde
            if ($this->input('section') === 'configuraciones' && $this->input('key') === 'ticket_en_pdf') {
                if (!is_bool($this->input('value'))) {
                    $validator->errors()->add('value', 'El valor debe ser verdadero o falso.');
                }
            }
        });
    }
}

