<?php

namespace App\Http\Requests\Ventas;

use Illuminate\Foundation\Http\FormRequest;

class IndexVentaRequest extends FormRequest
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
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Convertir recurrente a booleano si está presente
        if ($this->has('recurrente')) {
            $recurrente = $this->input('recurrente');
            
            // Si es null o string vacío, eliminar el campo
            if ($recurrente === null || $recurrente === '') {
                $this->offsetUnset('recurrente');
            } elseif (!is_bool($recurrente)) {
                // Convertir a booleano: acepta true, false, "true", "false", "1", "0", 1, 0
                $this->merge([
                    'recurrente' => filter_var($recurrente, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $recurrente
                ]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'inicio'            => 'nullable|date',
            'fin'               => 'nullable|date',
            'recurrente'        => 'nullable|boolean',
            'num_identificacion' => 'nullable|string',
            'id_sucursal'       => 'nullable|integer',
            'id_bodega'         => 'nullable|integer',
            'id_cliente'        => 'nullable|integer',
            'id_usuario'        => 'nullable|integer',
            'forma_pago'        => 'nullable|string',
            'id_vendedor'       => 'nullable|integer',
            'id_canal'          => 'nullable|integer',
            'id_proyecto'       => 'nullable|integer',
            'id_documento'      => 'nullable|integer',
            'estado'            => 'nullable|string',
            'metodo_pago'       => 'nullable|string',
            'tipo_documento'    => 'nullable|string',
            'dte'               => 'nullable|integer|in:1,2',
            'buscador'          => 'nullable|string',
            'orden'             => 'nullable|string',
            'direccion'         => 'nullable|string|in:asc,desc',
            'paginate'          => 'nullable|integer|min:1',
        ];
    }
}


