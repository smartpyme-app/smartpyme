<?php

namespace App\Http\Requests\Inventario\Paquetes;

use Illuminate\Foundation\Http\FormRequest;

class StorePaqueteRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:paquetes,id'],
            'fecha' => ['required', 'date', 'max:255'],
            'wr' => ['required', 'string', 'max:255'],
            'estado' => ['required', 'string', 'max:255'],
            'num_guia' => ['required', 'string', 'max:255'],
            'piezas' => ['required', 'numeric', 'min:0'],
            'peso' => ['required', 'numeric', 'min:0'],
            'precio' => ['required', 'numeric', 'min:0'],
            'id_cliente' => ['required', 'integer', 'exists:clientes,id'],
            'id_usuario' => ['required', 'integer', 'exists:users,id'],
            'id_sucursal' => ['required', 'integer', 'exists:sucursales,id'],
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'El paquete seleccionado no existe.',
            'fecha.required' => 'La fecha es requerida.',
            'fecha.date' => 'La fecha debe ser una fecha válida.',
            'fecha.max' => 'La fecha no puede exceder 255 caracteres.',
            'wr.required' => 'El WR es requerido.',
            'wr.max' => 'El WR no puede exceder 255 caracteres.',
            'estado.required' => 'El estado es requerido.',
            'estado.max' => 'El estado no puede exceder 255 caracteres.',
            'num_guia.required' => 'El número de guía es requerido.',
            'num_guia.max' => 'El número de guía no puede exceder 255 caracteres.',
            'piezas.required' => 'Las piezas son requeridas.',
            'piezas.numeric' => 'Las piezas deben ser un número.',
            'piezas.min' => 'Las piezas deben ser mayor o igual a 0.',
            'peso.required' => 'El peso es requerido.',
            'peso.numeric' => 'El peso debe ser un número.',
            'peso.min' => 'El peso debe ser mayor o igual a 0.',
            'precio.required' => 'El precio es requerido.',
            'precio.numeric' => 'El precio debe ser un número.',
            'precio.min' => 'El precio debe ser mayor o igual a 0.',
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
}

