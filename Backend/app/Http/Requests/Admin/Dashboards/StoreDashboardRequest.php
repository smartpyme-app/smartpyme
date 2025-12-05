<?php

namespace App\Http\Requests\Admin\Dashboards;

use Illuminate\Foundation\Http\FormRequest;

class StoreDashboardRequest extends FormRequest
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
            'titulo' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'string', 'max:255'],
            'codigo_embed' => ['required', 'string', 'max:900'],
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
            'id' => ['nullable', 'integer', 'exists:dashboards,id'],
            'plataforma' => ['nullable', 'string'],
            'codigo_embed_movil' => ['nullable', 'string'],
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
            'tipo.required' => 'El tipo es requerido.',
            'tipo.max' => 'El tipo no puede exceder 255 caracteres.',
            'codigo_embed.required' => 'El código embed es requerido.',
            'codigo_embed.max' => 'El código embed no puede exceder 900 caracteres.',
            'id_empresa.required' => 'La empresa es requerida.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'id.exists' => 'El dashboard seleccionado no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar strings
        if ($this->has('titulo')) {
            $this->merge(['titulo' => trim($this->titulo)]);
        }

        if ($this->has('tipo')) {
            $this->merge(['tipo' => trim($this->tipo)]);
        }
    }
}

