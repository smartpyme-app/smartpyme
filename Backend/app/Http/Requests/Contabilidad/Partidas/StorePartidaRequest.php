<?php

namespace App\Http\Requests\Contabilidad\Partidas;

use Illuminate\Foundation\Http\FormRequest;

class StorePartidaRequest extends FormRequest
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
            'id' => ['sometimes', 'nullable', 'integer', 'exists:partidas,id'],
            'fecha' => ['required', 'date'],
            'tipo' => ['required', 'string', 'max:255'],
            'concepto' => ['required', 'string', 'max:255'],
            'estado' => ['required', 'string', 'max:255'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id' => ['sometimes', 'nullable', 'integer', 'exists:partida_detalles,id'],
            'detalles.*.id_cuenta' => ['required', 'integer', 'exists:catalogo_cuentas,id'],
            'detalles.*.concepto' => ['nullable', 'string', 'max:255'],
            'detalles.*.debe' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.haber' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.saldo' => ['nullable', 'numeric'],
            'id_usuario' => ['required', 'integer', 'exists:users,id'],
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
            'correlativo' => ['nullable', 'string', 'max:255'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'id_referencia' => ['nullable', 'integer'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha es requerida.',
            'fecha.date' => 'La fecha debe ser una fecha válida.',
            'tipo.required' => 'El tipo es requerido.',
            'concepto.required' => 'El concepto es requerido.',
            'estado.required' => 'El estado es requerido.',
            'detalles.required' => 'Los detalles son requeridos.',
            'detalles.array' => 'Los detalles deben ser un arreglo.',
            'detalles.min' => 'Debe haber al menos un detalle.',
            'detalles.*.id_cuenta.required' => 'La cuenta contable es requerida para cada detalle.',
            'detalles.*.id_cuenta.exists' => 'Una o más cuentas contables no existen.',
            'detalles.*.debe.numeric' => 'El debe debe ser un número.',
            'detalles.*.haber.numeric' => 'El haber debe ser un número.',
            'id_usuario.required' => 'El usuario es requerido.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'id_empresa.required' => 'La empresa es requerida.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar concepto
        if ($this->has('concepto')) {
            $this->merge(['concepto' => trim($this->concepto)]);
        }

        // Normalizar valores decimales en detalles
        if ($this->has('detalles') && is_array($this->detalles)) {
            $detalles = [];
            foreach ($this->detalles as $detalle) {
                if (isset($detalle['debe']) && $detalle['debe'] !== null && $detalle['debe'] !== '') {
                    $detalle['debe'] = str_replace(',', '.', (string)$detalle['debe']);
                }
                if (isset($detalle['haber']) && $detalle['haber'] !== null && $detalle['haber'] !== '') {
                    $detalle['haber'] = str_replace(',', '.', (string)$detalle['haber']);
                }
                $detalles[] = $detalle;
            }
            $this->merge(['detalles' => $detalles]);
        }
    }
}

