<?php

namespace App\Http\Requests\Contabilidad;

use Illuminate\Foundation\Http\FormRequest;

class StoreProyectoRequest extends FormRequest
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
            'id' => ['sometimes', 'nullable', 'integer', 'exists:proyectos,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'estado' => ['required', 'string', 'max:255'],
            'id_cliente' => ['required', 'integer', 'exists:clientes,id'],
            'id_usuario' => ['required', 'integer', 'exists:users,id'],
            'id_sucursal' => ['required', 'integer', 'exists:sucursales,id'],
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
            'enable' => ['nullable', 'boolean'],
            'descripcion' => ['nullable', 'string'],
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
            'fecha_inicio.required' => 'La fecha de inicio es requerida.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_fin.required' => 'La fecha de fin es requerida.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',
            'estado.required' => 'El estado es requerido.',
            'id_cliente.required' => 'El cliente es requerido.',
            'id_cliente.exists' => 'El cliente seleccionado no existe.',
            'id_usuario.required' => 'El usuario es requerido.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'id_sucursal.required' => 'La sucursal es requerida.',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
            'id_empresa.required' => 'La empresa es requerida.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar nombre y descripcion
        if ($this->has('nombre')) {
            $this->merge(['nombre' => trim($this->nombre)]);
        }

        if ($this->has('descripcion')) {
            $this->merge(['descripcion' => trim($this->descripcion)]);
        }
    }
}

