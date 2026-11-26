<?php

namespace App\Http\Requests\Ventas;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreVentaProgramadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** exists restringido a la empresa del usuario autenticado (multi-tenant). */
    private function existe(string $tabla): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists($tabla, 'id')->where('id_empresa', $this->user()->id_empresa);
    }

    public function rules(): array
    {
        $empresa = $this->user()->id_empresa;
        // Las presentaciones no tienen id_empresa: pertenecen a un producto de la empresa.
        $presentacion = Rule::exists('producto_presentaciones', 'id')->where(
            fn (Builder $q) => $q->whereIn('id_producto', DB::table('productos')->where('id_empresa', $empresa)->select('id'))
        );
        // En array de reglas, "sometimes|required" como un solo string busca validateSometimes|required.
        $req = $this->isMethod('PUT') || $this->isMethod('PATCH')
            ? ['sometimes', 'required']
            : ['required'];

        return [
            'id_cliente' => [...$req, 'integer', $this->existe('clientes')],
            'id_documento' => [...$req, 'integer', $this->existe('documentos')],
            'frecuencia' => [...$req, 'in:mensual,anual'],
            'dia_ejecucion' => [...$req, 'integer', 'min:1', 'max:31'],
            'hora_ejecucion' => [...$req, 'date_format:H:i'],
            // Fecha de la próxima corrida (UI); el día del mes se deriva de esta fecha.
            'proxima_ejecucion' => 'nullable|date',
            'detalles' => [...$req, 'array', 'min:1'],
            'detalles.*.id_producto' => ['required', 'integer', $this->existe('productos')],
            'detalles.*.cantidad' => 'required|numeric|min:0.01',
            'detalles.*.precio' => 'required|numeric|min:0',
            'detalles.*.total' => 'required|numeric|min:0',
            'detalles.*.id_presentacion' => ['nullable', 'integer', $presentacion],
            'detalles.*.descripcion' => 'nullable|string',
            'detalles.*.precio_sin_iva' => 'nullable|numeric|min:0',
            'detalles.*.precio_con_iva' => 'nullable|numeric|min:0',
            'detalles.*.costo' => 'nullable|numeric|min:0',
            'detalles.*.descuento' => 'nullable|numeric|min:0',
            'detalles.*.sub_total' => 'nullable|numeric|min:0',
            'detalles.*.tipo_gravado' => 'nullable|string|max:20',
            'detalles.*.no_sujeta' => 'nullable|numeric|min:0',
            'detalles.*.exenta' => 'nullable|numeric|min:0',
            'detalles.*.gravada' => 'nullable|numeric|min:0',
            'detalles.*.cuenta_a_terceros' => 'nullable|numeric|min:0',
            'detalles.*.total_costo' => 'nullable|numeric|min:0',
            'detalles.*.id_vendedor' => ['nullable', 'integer', $this->existe('users')],
            'detalles.*.iva' => 'nullable|numeric|min:0',
            'detalles.*.porcentaje_impuesto' => 'nullable|numeric|min:0',
            'detalles.*.fe_cr_exoneracion' => 'nullable|array',
            'id_sucursal' => ['nullable', 'integer', $this->existe('sucursales')],
            'id_bodega' => ['nullable', 'integer', $this->existe('sucursal_bodegas')],
            'id_vendedor' => ['nullable', 'integer', $this->existe('users')],
            'forma_pago' => 'nullable|string|max:255',
            'condicion' => 'nullable|string|max:255',
            'sub_total' => 'nullable|numeric|min:0',
            'descuento' => 'nullable|numeric|min:0',
            'iva' => 'nullable|numeric|min:0',
            'total' => 'nullable|numeric|min:0',
            'gravada' => 'nullable|numeric|min:0',
            'exenta' => 'nullable|numeric|min:0',
            'no_sujeta' => 'nullable|numeric|min:0',
            'observaciones' => 'nullable|string',
            'activo' => 'sometimes|boolean',
            'emitir_automatico' => 'sometimes|boolean',
            'enviar_email' => 'sometimes|boolean',
            'max_ejecuciones' => 'nullable|integer|min:1',
        ];
    }
}
