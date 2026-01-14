<?php

namespace App\Http\Requests\Ventas;

use Illuminate\Foundation\Http\FormRequest;

class FacturacionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'fecha'             => 'required',
            'estado'            => 'required|max:255',
            'correlativo'       => 'required|numeric',
            'id_documento'      => 'required|max:255',
            'id_cliente'        => 'required_if:estado,"Pendiente"',
            'detalles'          => 'required',
            'fecha_expiracion'  => 'required_if:cotizacion,1',
            'fecha_pago'        => 'nullable|date',
            'descripcion_impresion'  => 'required_if:descripcion_personalizada,1',
            'credito'           => 'required_if:condicion,"Crédito"',
            'iva'               => 'required|numeric',
            'forma_pago'        => 'required_if:metodo_pago,"Crédito"',
            'total_costo'       => 'required|numeric',
            'sub_total'         => 'required|numeric',
            'total'             => 'required|numeric',
            'nota'              => 'max:255',
            'id_usuario'        => 'required|numeric',
            'id_bodega'         => 'required|numeric',
            'id_sucursal'       => 'required|numeric',
            'id_vendedor'       => 'nullable|numeric|exists:users,id',
            'monto_pago'        => 'nullable|numeric',
            'cambio'            => 'nullable|numeric',
            'observaciones'     => 'nullable|string',
            'tipo_operacion'    => 'nullable|string|max:255',
        ];

        // id_canal solo es requerido cuando NO es cotización
        if (!$this->cotizacion || $this->cotizacion != 1) {
            $rules['id_canal'] = 'required|max:255';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'detalles.required' => 'Tiene que agregar productos',
            'id_cliente.required_if' => 'El cliente es requerido para los creditos y la facturación.',
            'fecha_expiracion.required_if' => 'La fecha de expiracion es obligatorio cuando es cotización.',
            'id_canal.required' => 'El campo id canal es obligatorio.',
        ];
    }
}


