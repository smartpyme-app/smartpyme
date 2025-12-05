<?php

namespace App\Http\Requests\Admin\Notificaciones;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotificacionRequest extends FormRequest
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
            'id' => 'sometimes|nullable|integer|exists:notificaciones,id',
            'descripcion' => 'required|string|max:500',
            'titulo' => 'sometimes|nullable|string|max:255',
            'tipo' => 'required|string|max:255',
            'categoria' => 'sometimes|nullable|string|max:255',
            'prioridad' => 'required|string|max:255',
            'id_empresa' => 'required|integer|exists:empresas,id',
            'id_sucursal' => 'sometimes|nullable|integer|exists:sucursales,id',
            'id_orden_produccion' => 'sometimes|nullable|integer',
            'referencia' => 'sometimes|nullable|string|max:255',
            'id_referencia' => 'sometimes|nullable|integer',
            'leido' => 'sometimes|nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'La notificación no existe.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'descripcion.max' => 'La descripción no puede exceder 500 caracteres.',
            'titulo.max' => 'El título no puede exceder 255 caracteres.',
            'tipo.required' => 'El tipo de notificación es obligatorio.',
            'tipo.max' => 'El tipo no puede exceder 255 caracteres.',
            'categoria.max' => 'La categoría no puede exceder 255 caracteres.',
            'prioridad.required' => 'La prioridad es obligatoria.',
            'prioridad.max' => 'La prioridad no puede exceder 255 caracteres.',
            'id_empresa.required' => 'La empresa es obligatoria.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
            'referencia.max' => 'La referencia no puede exceder 255 caracteres.',
            'leido.boolean' => 'El estado leído debe ser verdadero o falso.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'descripcion' => 'descripción',
            'titulo' => 'título',
            'tipo' => 'tipo',
            'categoria' => 'categoría',
            'prioridad' => 'prioridad',
            'id_empresa' => 'empresa',
            'id_sucursal' => 'sucursal',
            'id_orden_produccion' => 'orden de producción',
            'referencia' => 'referencia',
            'id_referencia' => 'ID de referencia',
            'leido' => 'leído',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar strings
        if ($this->has('titulo') && $this->titulo) {
            $this->merge([
                'titulo' => trim($this->titulo),
            ]);
        }

        if ($this->has('descripcion')) {
            $this->merge([
                'descripcion' => trim($this->descripcion),
            ]);
        }

        if ($this->has('tipo')) {
            $this->merge([
                'tipo' => trim($this->tipo),
            ]);
        }

        if ($this->has('categoria') && $this->categoria) {
            $this->merge([
                'categoria' => trim($this->categoria),
            ]);
        }

        if ($this->has('prioridad')) {
            $this->merge([
                'prioridad' => trim($this->prioridad),
            ]);
        }

        if ($this->has('referencia') && $this->referencia) {
            $this->merge([
                'referencia' => trim($this->referencia),
            ]);
        }

        // Convertir leido a boolean
        if ($this->has('leido')) {
            $this->merge([
                'leido' => filter_var($this->leido, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $this->leido,
            ]);
        }
    }
}

