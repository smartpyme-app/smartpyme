<?php

namespace App\Http\Requests\Ventas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FacturacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return static::rulesFor($this->all());
    }

    public function messages(): array
    {
        return static::validationMessages();
    }

    /**
     * Reglas compartidas (app JWT y API externa vía FacturacionService).
     *
     * @param  array<string, mixed>|null  $input
     * @return array<string, mixed>
     */
    public static function rulesFor(?array $input = null): array
    {
        $input = $input ?? [];
        $cotizacion = (int) ($input['cotizacion'] ?? 0);

        $rules = [
            'fecha'                   => 'required',
            'estado'                  => 'required|max:255',
            'correlativo'             => 'nullable|numeric',
            'id_documento'            => 'required|max:255',
            'id_cliente'              => 'required_if:estado,"Pendiente"',
            'detalles'                => 'required|array|min:1',
            'fecha_expiracion'        => 'required_if:cotizacion,1',
            'fecha_pago'              => 'nullable|date',
            'descripcion_impresion'   => 'required_if:descripcion_personalizada,1',
            'credito'                 => 'required_if:condicion,"Crédito"',
            'iva'                     => 'required|numeric',
            'forma_pago'              => 'required_if:metodo_pago,"Crédito"',
            'total_costo'             => 'required|numeric',
            'sub_total'               => 'required|numeric',
            'total'                   => 'required|numeric',
            'nota'                    => 'max:255',
            'id_usuario'              => 'required|numeric',
            'id_bodega'               => 'required|numeric',
            'id_sucursal'             => 'required|numeric',
            'id_vendedor'             => 'nullable|numeric|exists:users,id',
            'monto_pago'              => 'nullable|numeric',
            'cambio'                  => 'nullable|numeric',
            'observaciones'           => 'nullable|string',
            'tipo_operacion'          => 'nullable|string|max:255',
            'referencia'              => 'nullable|string|max:255',
            'cotizacion'              => 'nullable|boolean',
            'id_credito_cuota'        => 'nullable|integer',
            'credito_contrato'        => 'nullable|array',
            'credito_contrato.tipo'   => 'required_with:credito_contrato|in:bien,servicio,prestamo',
            'credito_contrato.monto'  => 'required_with:credito_contrato|numeric|gt:0',
            'credito_contrato.n_cuotas' => 'required_with:credito_contrato|integer|min:2',
            'credito_contrato.fecha_inicio' => 'required_with:credito_contrato|date',
            'credito_contrato.concepto' => 'nullable|string|max:255',
            'credito_contrato.tasa_interes' => 'nullable|numeric|min:0',
            'credito_contrato.tasa_mora' => 'nullable|numeric|min:0',
            'credito_contrato.cuotas' => 'nullable|array|min:2',
            'credito_contrato.cuotas.*.numero' => 'required_with:credito_contrato.cuotas|integer|min:1',
            'credito_contrato.cuotas.*.monto' => 'required_with:credito_contrato.cuotas|numeric|gt:0',
        ];

        if ($cotizacion !== 1) {
            $rules['id_canal'] = 'required|max:255';
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function validationMessages(): array
    {
        return [
            'detalles.required' => 'Tiene que agregar productos',
            'detalles.min' => 'Tiene que agregar productos',
            'id_cliente.required_if' => 'El cliente es requerido para los creditos y la facturación.',
            'fecha_expiracion.required_if' => 'La fecha de expiracion es obligatorio cuando es cotización.',
            'id_canal.required' => 'El campo id canal es obligatorio.',
        ];
    }

    /**
     * Valida un payload de facturación (p. ej. API externa) con las mismas reglas que la app.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validatePayload(array $data): array
    {
        $validator = Validator::make(
            $data,
            static::rulesFor($data),
            static::validationMessages()
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
