<?php

namespace App\Http\Requests\Contabilidad;

use Illuminate\Foundation\Http\FormRequest;

class StoreConfiguracionRequest extends FormRequest
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
            'id' => ['sometimes', 'nullable', 'integer', 'exists:contabilidad_configuracion,id'],
            'id_cuenta_ventas' => ['required', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_devoluciones_ventas' => ['required', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_iva_ventas' => ['required', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_iva_retenido_ventas' => ['required', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_renta_retenida_ventas' => ['required', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_cxc' => ['required', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_costo_venta' => ['required', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_inventario' => ['required', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_cxp' => ['required', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_devoluciones_proveedores' => ['required', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_iva_compras' => ['required', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_iva_retenido_compras' => ['required', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_renta_retenida_compras' => ['required', 'integer', 'exists:catalogo_cuentas,id'],
            'generar_partidas' => ['required', 'boolean'],
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id_cuenta_ventas.required' => 'La cuenta para ingresos es requerida',
            'id_cuenta_ventas.exists' => 'La cuenta para ingresos no existe.',
            'id_cuenta_devoluciones_ventas.required' => 'La cuenta para devoluciones ventas es requerida',
            'id_cuenta_devoluciones_ventas.exists' => 'La cuenta para devoluciones ventas no existe.',
            'id_cuenta_iva_ventas.required' => 'La cuenta para IVA de ventas es requerida',
            'id_cuenta_iva_ventas.exists' => 'La cuenta para IVA de ventas no existe.',
            'id_cuenta_iva_retenido_ventas.required' => 'La cuenta para IVA retenida de ventas es requerida',
            'id_cuenta_iva_retenido_ventas.exists' => 'La cuenta para IVA retenida de ventas no existe.',
            'id_cuenta_renta_retenida_ventas.required' => 'La cuenta para Renta retenida de ventas es requerida',
            'id_cuenta_renta_retenida_ventas.exists' => 'La cuenta para Renta retenida de ventas no existe.',
            'id_cuenta_cxc.required' => 'La cuenta para cxc es requerida',
            'id_cuenta_cxc.exists' => 'La cuenta para cxc no existe.',
            'id_cuenta_costo_venta.required' => 'La cuenta para costo de venta es requerida',
            'id_cuenta_costo_venta.exists' => 'La cuenta para costo de venta no existe.',
            'id_cuenta_inventario.required' => 'La cuenta para inventario es requerida',
            'id_cuenta_inventario.exists' => 'La cuenta para inventario no existe.',
            'id_cuenta_cxp.required' => 'La cuenta para cxp es requerida',
            'id_cuenta_cxp.exists' => 'La cuenta para cxp no existe.',
            'id_cuenta_devoluciones_proveedores.required' => 'La cuenta para devoluciones proveedores es requerida',
            'id_cuenta_devoluciones_proveedores.exists' => 'La cuenta para devoluciones proveedores no existe.',
            'id_cuenta_iva_compras.required' => 'La cuenta para IVA de compras es requerida',
            'id_cuenta_iva_compras.exists' => 'La cuenta para IVA de compras no existe.',
            'id_cuenta_iva_retenido_compras.required' => 'La cuenta para IVA retenida de compras es requerida',
            'id_cuenta_iva_retenido_compras.exists' => 'La cuenta para IVA retenida de compras no existe.',
            'id_cuenta_renta_retenida_compras.required' => 'La cuenta para Renta retenida de compras es requerida',
            'id_cuenta_renta_retenida_compras.exists' => 'La cuenta para Renta retenida de compras no existe.',
            'generar_partidas.required' => 'El campo generar partidas es requerido.',
            'generar_partidas.boolean' => 'El campo generar partidas debe ser un booleano.',
            'id_empresa.required' => 'La empresa es requerida.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
        ];
    }
}

