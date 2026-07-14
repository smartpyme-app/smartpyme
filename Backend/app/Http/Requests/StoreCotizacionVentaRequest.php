<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCotizacionVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // ponytail: validación laxa alineada al payload de FacturacionComponent; techo = FormRequest estricto por país/empresa
        return [
            'fecha' => ['required', 'date'],
            'total' => ['required', 'numeric', 'min:0'],
            'id_cliente' => ['required', 'integer', 'exists:clientes,id'],
            'id_usuario' => ['required', 'integer', 'exists:users,id'],
            'id_sucursal' => ['required', 'integer', 'exists:sucursales,id'],
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
            'id_vendedor' => ['nullable', 'integer', 'exists:users,id'],
            'id_proyecto' => ['nullable', 'integer', 'exists:proyectos,id'],
            'id_documento' => ['nullable', 'integer'],
            'observaciones' => ['nullable', 'string'],
            'fecha_expiracion' => ['nullable', 'date'],
            'estado' => ['nullable', 'string'],
            'cobrar_impuestos' => ['nullable'],
            'retencion' => ['nullable'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id_producto' => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.precio' => ['required', 'numeric', 'min:0'],
            'detalles.*.total' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha es requerida',
            'total.required' => 'El total es requerido',
            'id_cliente.required' => 'El cliente es requerido',
            'id_cliente.exists' => 'El cliente seleccionado no existe.',
            'detalles.required' => 'Ingresa por lo menos 1 detalle',
            'detalles.min' => 'Debe haber al menos un detalle.',
            'detalles.*.id_producto.required' => 'El producto es requerido para cada detalle.',
            'detalles.*.id_producto.exists' => 'Uno o más productos no existen.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('observaciones') && is_string($this->observaciones)) {
            $this->merge(['observaciones' => trim($this->observaciones)]);
        }

        if ($this->has('total')) {
            $this->merge(['total' => (float) $this->total]);
        }

        // Facturacion usa Pendiente; listado/acciones usan minúsculas
        if ($this->has('estado') && is_string($this->estado)) {
            $this->merge(['estado' => strtolower($this->estado)]);
        }
    }
}
