<?php

namespace App\Http\Requests\Eventos;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventoRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:eventos,id'],
            'descripcion' => ['required', 'string', 'max:500'],
            'id_cliente' => ['required', 'integer', 'exists:clientes,id'],
            'frecuencia_fin' => ['required_with:frecuencia', 'date', 'after_or_equal:inicio'],
            'inicio' => ['required', 'date'],
            'fin' => ['nullable', 'date', 'after_or_equal:inicio'],
            'frecuencia' => ['nullable', 'string'],
            'tipo' => ['nullable', 'string'],
            'estado' => ['nullable', 'string'],
            'duracion' => ['nullable', 'numeric'],
            'id_servicio' => ['nullable', 'integer', 'exists:servicios,id'],
            'id_venta' => ['nullable', 'integer', 'exists:ventas,id'],
            'id_usuario' => ['nullable', 'integer', 'exists:users,id'],
            'id_sucursal' => ['nullable', 'integer', 'exists:sucursales,id'],
            'id_empresa' => ['nullable', 'integer', 'exists:empresas,id'],
            'detalles' => ['nullable', 'string'],
            'productos' => ['nullable', 'array'],
            'productos.*.id' => ['nullable', 'integer', 'exists:detalles_evento,id'],
            'productos.*.id_producto' => ['required_with:productos', 'integer', 'exists:productos,id'],
            'productos.*.cantidad' => ['required_with:productos', 'numeric', 'min:0.01'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'descripcion.required' => 'El campo título es obligatorio.',
            'descripcion.max' => 'La descripción no puede exceder 500 caracteres.',
            'id_cliente.required' => 'El campo cliente es obligatorio.',
            'id_cliente.exists' => 'El cliente seleccionado no existe.',
            'frecuencia_fin.required_with' => 'El campo terminar de repetir es obligatorio cuando se especifica frecuencia.',
            'frecuencia_fin.date' => 'La fecha de fin de frecuencia debe ser una fecha válida.',
            'frecuencia_fin.after_or_equal' => 'La fecha de fin de frecuencia debe ser igual o posterior a la fecha de inicio.',
            'inicio.required' => 'La fecha de inicio es requerida.',
            'inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'id_servicio.exists' => 'El servicio seleccionado no existe.',
            'id_venta.exists' => 'La venta seleccionada no existe.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'id.exists' => 'El evento seleccionado no existe.',
            'productos.*.id.exists' => 'Uno de los detalles de evento seleccionados no existe.',
            'productos.*.id_producto.required_with' => 'El producto es requerido para cada detalle.',
            'productos.*.id_producto.exists' => 'Uno de los productos seleccionados no existe.',
            'productos.*.cantidad.required_with' => 'La cantidad es requerida para cada detalle.',
            'productos.*.cantidad.numeric' => 'La cantidad debe ser un número.',
            'productos.*.cantidad.min' => 'La cantidad debe ser mayor a 0.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Limpiar strings
        if ($this->has('descripcion')) {
            $this->merge(['descripcion' => trim($this->descripcion)]);
        }

        if ($this->has('detalles')) {
            $this->merge(['detalles' => trim($this->detalles)]);
        }

        // Convertir valores numéricos en productos
        if ($this->has('productos') && is_array($this->productos)) {
            $productos = [];
            foreach ($this->productos as $producto) {
                if (isset($producto['cantidad'])) {
                    $producto['cantidad'] = (float) $producto['cantidad'];
                }
                $productos[] = $producto;
            }
            $this->merge(['productos' => $productos]);
        }

        if ($this->has('duracion')) {
            $this->merge(['duracion' => (float) $this->duracion]);
        }
    }
}

