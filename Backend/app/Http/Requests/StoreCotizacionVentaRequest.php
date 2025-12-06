<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCotizacionVentaRequest extends FormRequest
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
            'observaciones' => ['required', 'string'],
            'fecha_expiracion' => ['required', 'date'],
            'fecha' => ['required', 'date'],
            'total' => ['required', 'numeric', 'min:0'],
            'id_cliente' => ['required', 'integer', 'exists:clientes,id'],
            'id_proyecto' => ['required', 'integer', 'exists:proyectos,id'],
            'id_usuario' => ['required', 'integer', 'exists:users,id'],
            'id_vendedor' => ['required', 'integer', 'exists:users,id'],
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
            'id_sucursal' => ['required', 'integer', 'exists:sucursales,id'],
            'cobrar_impuestos' => ['required', 'boolean'],
            'retencion' => ['required', 'boolean'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.precio' => ['required', 'numeric', 'min:0'],
            'detalles.*.total' => ['required', 'numeric', 'min:0'],
            'detalles.*.total_costo' => ['required', 'numeric', 'min:0'],
            'detalles.*.descuento' => ['required', 'numeric', 'min:0'],
            'detalles.*.no_sujeta' => ['required', 'numeric', 'min:0'],
            'detalles.*.exenta' => ['required', 'numeric', 'min:0'],
            'detalles.*.cuenta_a_terceros' => ['required', 'numeric', 'min:0'],
            'detalles.*.gravada' => ['required', 'numeric', 'min:0'],
            'detalles.*.iva' => ['required', 'numeric', 'min:0'],
            'detalles.*.descripcion' => ['required', 'string'],
            'detalles.*.id_producto' => ['required', 'integer', 'exists:productos,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'observaciones.required' => 'Las observaciones son requeridas',
            'fecha_expiracion.required' => 'La fecha de expiración es requerida',
            'fecha_expiracion.date' => 'La fecha de expiración debe ser una fecha válida.',
            'fecha.required' => 'La fecha es requerida',
            'fecha.date' => 'La fecha debe ser una fecha válida.',
            'total.required' => 'El total es requerido',
            'total.numeric' => 'El total debe ser un número.',
            'total.min' => 'El total no puede ser negativo.',
            'id_cliente.required' => 'El cliente es requerido',
            'id_cliente.exists' => 'El cliente seleccionado no existe.',
            'id_proyecto.required' => 'El proyecto es requerido',
            'id_proyecto.exists' => 'El proyecto seleccionado no existe.',
            'id_usuario.required' => 'El usuario es requerido',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'id_vendedor.required' => 'El vendedor es requerido',
            'id_vendedor.exists' => 'El vendedor seleccionado no existe.',
            'id_empresa.required' => 'La empresa es requerida',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'id_sucursal.required' => 'La sucursal es requerida',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
            'cobrar_impuestos.required' => 'El campo cobrar impuestos es requerido.',
            'cobrar_impuestos.boolean' => 'El campo cobrar impuestos debe ser un booleano.',
            'retencion.required' => 'El campo retención es requerido.',
            'retencion.boolean' => 'El campo retención debe ser un booleano.',
            'detalles.required' => 'Ingresa por lo menos 1 detalle',
            'detalles.array' => 'Los detalles deben ser un arreglo.',
            'detalles.min' => 'Debe haber al menos un detalle.',
            'detalles.*.cantidad.required' => 'La cantidad es requerida para cada detalle.',
            'detalles.*.cantidad.min' => 'La cantidad debe ser mayor a 0.',
            'detalles.*.precio.required' => 'El precio es requerido para cada detalle.',
            'detalles.*.precio.min' => 'El precio no puede ser negativo.',
            'detalles.*.total.required' => 'El total es requerido para cada detalle.',
            'detalles.*.total.min' => 'El total no puede ser negativo.',
            'detalles.*.total_costo.required' => 'El total de costo es requerido para cada detalle.',
            'detalles.*.total_costo.min' => 'El total de costo no puede ser negativo.',
            'detalles.*.descuento.required' => 'El descuento es requerido para cada detalle.',
            'detalles.*.descuento.min' => 'El descuento no puede ser negativo.',
            'detalles.*.no_sujeta.required' => 'El campo no sujeta es requerido para cada detalle.',
            'detalles.*.no_sujeta.min' => 'El campo no sujeta no puede ser negativo.',
            'detalles.*.exenta.required' => 'El campo exenta es requerido para cada detalle.',
            'detalles.*.exenta.min' => 'El campo exenta no puede ser negativo.',
            'detalles.*.cuenta_a_terceros.required' => 'El campo cuenta a terceros es requerido para cada detalle.',
            'detalles.*.cuenta_a_terceros.min' => 'El campo cuenta a terceros no puede ser negativo.',
            'detalles.*.gravada.required' => 'El campo gravada es requerido para cada detalle.',
            'detalles.*.gravada.min' => 'El campo gravada no puede ser negativo.',
            'detalles.*.iva.required' => 'El IVA es requerido para cada detalle.',
            'detalles.*.iva.min' => 'El IVA no puede ser negativo.',
            'detalles.*.descripcion.required' => 'La descripción es requerida para cada detalle.',
            'detalles.*.id_producto.required' => 'El producto es requerido para cada detalle.',
            'detalles.*.id_producto.exists' => 'Uno o más productos no existen.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar observaciones
        if ($this->has('observaciones')) {
            $this->merge(['observaciones' => trim($this->observaciones)]);
        }

        // Convertir valores numéricos
        if ($this->has('total')) {
            $this->merge(['total' => (float) $this->total]);
        }
    }
}

