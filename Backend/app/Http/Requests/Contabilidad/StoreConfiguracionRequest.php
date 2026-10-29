<?php

namespace App\Http\Requests\Contabilidad;

use App\Models\Admin\Empresa;
use App\Models\EmpresaConfiguracion;
use App\Models\EmpresaConfiguracionPlanilla;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'id_cuenta_iva_ventas_cf' => ['nullable', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_iva_retenido_ventas' => ['required', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_renta_retenida_ventas' => ['required', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_cxc' => ['required', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_costo_venta' => ['required', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_inventario' => ['required', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_cxp' => ['required', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_devoluciones_proveedores' => ['required', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_iva_compras' => ['required', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_iva_compras_cf' => ['nullable', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_iva_retenido_compras' => ['required', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_renta_retenida_compras' => ['required', 'integer', 'exists:catalogo_cuentas,id'],
            'generar_partidas' => ['required', 'string', 'in:Manual,Auto'],
            'separar_cuentas_iva' => ['sometimes', 'boolean'],
            'abonos_en_cartera' => ['sometimes', 'boolean'],
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
            'id_cuenta_gasto_salarios' => ['nullable', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_gasto_cargas_patronales' => ['nullable', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_gasto_aguinaldo' => ['nullable', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_gasto_vacaciones' => ['nullable', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_pasivo_cargas_sociales' => ['nullable', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_pasivo_ins' => ['nullable', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_pasivo_retencion_renta' => ['nullable', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_pasivo_salarios_por_pagar' => ['nullable', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_pasivo_provision_aguinaldo' => ['nullable', 'integer', 'exists:catalogo_cuentas,id'],
            'id_cuenta_pasivo_provision_vacaciones' => ['nullable', 'integer', 'exists:catalogo_cuentas,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        $nullableCuentas = [
            'id_cuenta_iva_ventas_cf',
            'id_cuenta_iva_compras_cf',
            'id_cuenta_gasto_salarios',
            'id_cuenta_gasto_cargas_patronales',
            'id_cuenta_gasto_aguinaldo',
            'id_cuenta_gasto_vacaciones',
            'id_cuenta_pasivo_cargas_sociales',
            'id_cuenta_pasivo_ins',
            'id_cuenta_pasivo_retencion_renta',
            'id_cuenta_pasivo_salarios_por_pagar',
            'id_cuenta_pasivo_provision_aguinaldo',
            'id_cuenta_pasivo_provision_vacaciones',
        ];
        foreach ($nullableCuentas as $campo) {
            if ($this->input($campo) === '' || $this->input($campo) === 'null') {
                $merge[$campo] = null;
            }
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (!$this->planillasModuloActivo()) {
                return;
            }

            $requeridas = [
                'id_cuenta_gasto_salarios' => 'Gasto de salarios',
                'id_cuenta_gasto_cargas_patronales' => 'Gasto de cargas patronales',
                'id_cuenta_gasto_aguinaldo' => 'Gasto de provisión de aguinaldo',
                'id_cuenta_gasto_vacaciones' => 'Gasto de provisión de vacaciones',
                'id_cuenta_pasivo_cargas_sociales' => 'Pasivo de cargas sociales',
                'id_cuenta_pasivo_retencion_renta' => 'Pasivo de retención de renta',
                'id_cuenta_pasivo_salarios_por_pagar' => 'Salarios por pagar',
                'id_cuenta_pasivo_provision_aguinaldo' => 'Provisión de aguinaldo por pagar',
                'id_cuenta_pasivo_provision_vacaciones' => 'Provisión de vacaciones por pagar',
            ];

            if ($this->codPaisEmpresa() === 'CR') {
                $requeridas['id_cuenta_pasivo_ins'] = 'INS riesgos del trabajo por pagar';
            }

            foreach ($requeridas as $campo => $etiqueta) {
                if ($this->input($campo) === null || $this->input($campo) === '') {
                    $v->errors()->add($campo, "La cuenta de planilla «{$etiqueta}» es requerida mientras el módulo de planillas está activo.");
                }
            }
        });
    }

    private function planillasModuloActivo(): bool
    {
        $empresaId = (int) $this->input('id_empresa');
        if (EmpresaConfiguracion::porEmpresa($empresaId)
            ->modulo(EmpresaConfiguracion::MODULO_PLANILLAS)
            ->exists()) {
            return true;
        }

        return EmpresaConfiguracionPlanilla::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->exists();
    }

    private function codPaisEmpresa(): ?string
    {
        $empresa = Empresa::find((int) $this->input('id_empresa'));

        return $empresa
            ? EmpresaConfiguracionPlanilla::resolverCodigoPaisEmpresa($empresa)
            : null;
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
            'generar_partidas.in' => 'El campo generar partidas debe ser Manual o Auto.',
            'id_empresa.required' => 'La empresa es requerida.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
        ];
    }
}

