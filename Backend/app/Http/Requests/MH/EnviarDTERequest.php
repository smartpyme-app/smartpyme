<?php

namespace App\Http\Requests\MH;

use App\Models\Compras\Compra;
use App\Models\Compras\Gastos\Gasto;
use App\Models\Ventas\Devoluciones\Devolucion as DevolucionVenta;
use App\Models\Ventas\Venta;
use Illuminate\Foundation\Http\FormRequest;

class EnviarDTERequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('tipo_dte')) {
            return;
        }

        $fromPayload = GenerarDTEAnuladoRequest::tipoDteFromDte($this->input('dte'));
        if ($fromPayload !== null) {
            $this->merge(['tipo_dte' => $fromPayload]);

            return;
        }

        $id = $this->input('id');
        if (! is_numeric($id)) {
            return;
        }

        $tipo = self::tipoDteFromRegistroId((int) $id, $this->input('tipo'));
        if ($tipo !== null) {
            $this->merge(['tipo_dte' => $tipo]);
        }
    }

    /**
     * Resuelve tipo_dte cuando el cliente envía solo id (p. ej. fila de ventas sin columna tipo_dte).
     */
    public static function tipoDteFromRegistroId(int $id, mixed $tipoRegistro = null): ?string
    {
        if ($tipoRegistro === 'compra') {
            $registro = Compra::query()->find($id);

            return $registro ? GenerarDTEAnuladoRequest::tipoDteFromRegistro($registro) : null;
        }

        if ($tipoRegistro === 'gasto') {
            $registro = Gasto::query()->find($id);

            return $registro ? GenerarDTEAnuladoRequest::tipoDteFromRegistro($registro) : null;
        }

        $venta = Venta::query()->find($id);
        if ($venta) {
            $tipo = GenerarDTEAnuladoRequest::tipoDteFromRegistro($venta);
            if ($tipo !== null) {
                return $tipo;
            }
        }

        $devolucion = DevolucionVenta::query()->find($id);
        if ($devolucion) {
            $tipo = GenerarDTEAnuladoRequest::tipoDteFromRegistro($devolucion);
            if ($tipo !== null) {
                return $tipo;
            }
        }

        $compra = Compra::query()->find($id);
        if ($compra) {
            $tipo = GenerarDTEAnuladoRequest::tipoDteFromRegistro($compra);
            if ($tipo !== null) {
                return $tipo;
            }
        }

        $gasto = Gasto::query()->find($id);
        if ($gasto) {
            return GenerarDTEAnuladoRequest::tipoDteFromRegistro($gasto);
        }

        return null;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer'],
            'tipo_dte' => ['required', 'string', 'in:01,02,03,04,05,06,08,09,11,14,18'],
            // 'tipo' solo aplica a compra/gasto (FEC 14/08). Para el resto (NC 03, factura 01, etc.) el frontend
            // envía el objeto completo del registro con su propio 'tipo' (p. ej. devolucion): excluirlo evita el
            // falso error "El tipo debe ser compra o gasto".
            'tipo' => ['exclude_unless:tipo_dte,14,08', 'required_if:tipo_dte,14,08', 'string', 'in:compra,gasto'],
            // Opcional. Vacío = correo del cliente o proveedor. Si viene, es el destinatario de este envío.
            'correo' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.required' => 'El ID del registro es requerido.',
            'id.integer' => 'El ID del registro debe ser un número entero.',
            'tipo_dte.required' => 'El tipo de DTE es requerido.',
            'tipo_dte.in' => 'El tipo de DTE debe ser uno de: 01, 02, 03, 04, 05, 06, 08, 09, 11, 14.',
            'tipo.required_if' => 'El tipo es requerido cuando el tipo de DTE es 14 u 08 (compra electrónica).',
            'tipo.in' => 'El tipo debe ser compra o gasto.',
            'correo.email' => 'El correo no es válido.',
            'correo.max' => 'El correo no puede superar 255 caracteres.',
        ];
    }
}

