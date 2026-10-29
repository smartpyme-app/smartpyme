<?php

namespace App\Http\Requests\MH;

use App\Models\Ventas\Devoluciones\Devolucion;
use App\Models\Ventas\Venta;
use Illuminate\Foundation\Http\FormRequest;

class GenerarDTEAnuladoRequest extends FormRequest
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

        $fromPayload = self::tipoDteFromDte($this->input('dte'));
        if ($fromPayload !== null) {
            $this->merge(['tipo_dte' => $fromPayload]);

            return;
        }

        $id = $this->input('id');
        if (! is_numeric($id)) {
            return;
        }

        $id = (int) $id;

        $venta = Venta::query()->find($id);
        if ($venta) {
            $tipo = self::tipoDteFromRegistro($venta);
            if ($tipo !== null) {
                $this->merge(['tipo_dte' => $tipo]);

                return;
            }
        }

        $devolucion = Devolucion::query()->find($id);
        if ($devolucion) {
            $tipo = self::tipoDteFromRegistro($devolucion);
            if ($tipo !== null) {
                $this->merge(['tipo_dte' => $tipo]);
            }
        }
    }

    /** @param object{tipo_dte?: mixed, dte?: mixed} $registro */
    public static function tipoDteFromRegistro(object $registro): ?string
    {
        $column = $registro->tipo_dte ?? null;
        if ($column !== null && $column !== '') {
            return (string) $column;
        }

        return self::tipoDteFromDte($registro->dte ?? null);
    }

    public static function tipoDteFromDte(mixed $dte): ?string
    {
        if (is_array($dte)) {
            $tipo = $dte['identificacion']['tipoDte'] ?? null;

            return ($tipo !== null && $tipo !== '') ? (string) $tipo : null;
        }

        if (is_string($dte) && $dte !== '') {
            $decoded = json_decode($dte, true);

            return is_array($decoded) ? self::tipoDteFromDte($decoded) : null;
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
            'tipo_dte' => ['required', 'string', 'in:01,03,05,06,11'],
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
            'tipo_dte.in' => 'El tipo de DTE debe ser uno de: 01, 03, 05, 06, 11.',
        ];
    }
}

