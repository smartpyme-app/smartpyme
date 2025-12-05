<?php

namespace App\Http\Requests\Contabilidad\Partidas;

use Illuminate\Foundation\Http\FormRequest;

class StoreDetallePartidaRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:partida_detalles,id'],
            'id_cuenta' => ['required', 'integer', 'exists:cuentas,id'],
            'codigo' => ['required', 'numeric'],
            'nombre_cuenta' => ['required', 'string'],
            'id_partida' => ['required', 'integer', 'exists:partidas,id'],
            'concepto' => ['required', 'string', 'max:255'],
            'cargo' => ['required', 'numeric', 'min:0'],
            'abono' => ['required', 'numeric', 'min:0'],
            'saldo' => ['required', 'numeric'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'El detalle seleccionado no existe.',
            'id_cuenta.required' => 'La cuenta es requerida.',
            'id_cuenta.exists' => 'La cuenta seleccionada no existe.',
            'codigo.required' => 'El código es requerido.',
            'codigo.numeric' => 'El código debe ser un número.',
            'nombre_cuenta.required' => 'El nombre de la cuenta es requerido.',
            'nombre_cuenta.string' => 'El nombre de la cuenta debe ser una cadena de texto.',
            'id_partida.required' => 'La partida es requerida.',
            'id_partida.exists' => 'La partida seleccionada no existe.',
            'concepto.required' => 'El concepto es requerido.',
            'concepto.max' => 'El concepto no puede exceder 255 caracteres.',
            'cargo.required' => 'El cargo es requerido.',
            'cargo.numeric' => 'El cargo debe ser un número.',
            'cargo.min' => 'El cargo debe ser mayor o igual a 0.',
            'abono.required' => 'El abono es requerido.',
            'abono.numeric' => 'El abono debe ser un número.',
            'abono.min' => 'El abono debe ser mayor o igual a 0.',
            'saldo.required' => 'El saldo es requerido.',
            'saldo.numeric' => 'El saldo debe ser un número.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convertir valores numéricos
        if ($this->has('codigo')) {
            $this->merge(['codigo' => (float) $this->codigo]);
        }

        if ($this->has('cargo')) {
            $this->merge(['cargo' => (float) $this->cargo]);
        }

        if ($this->has('abono')) {
            $this->merge(['abono' => (float) $this->abono]);
        }

        if ($this->has('saldo')) {
            $this->merge(['saldo' => (float) $this->saldo]);
        }

        // Limpiar strings
        if ($this->has('concepto')) {
            $this->merge(['concepto' => trim($this->concepto)]);
        }

        if ($this->has('nombre_cuenta')) {
            $this->merge(['nombre_cuenta' => trim($this->nombre_cuenta)]);
        }
    }
}

