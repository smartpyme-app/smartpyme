<?php

namespace App\Http\Requests\Bancos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChequeRequest extends FormRequest
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
            'id' => ['sometimes', 'nullable', 'integer', 'exists:cuentas_bancarias_cheques,id'],
            'fecha' => ['required', 'date'],
            'id_cuenta' => [
                'required',
                'integer',
                Rule::exists('cuentas_bancarias', 'id')->where(function ($query) {
                    return $query->where('id_empresa', $this->id_empresa);
                }),
            ],
            'correlativo' => ['required', 'integer', 'min:1'],
            'anombrede' => ['required', 'string', 'max:255'],
            'concepto' => ['required', 'string', 'max:255'],
            'total' => ['required', 'numeric', 'min:0'],
            'id_usuario' => ['required', 'integer', 'exists:users,id'],
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
            'estado' => ['nullable', 'string', 'max:255', 'in:Pendiente,Aprobado,Anulado'],
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
            'id_cuenta.required' => 'La cuenta bancaria es requerida.',
            'id_cuenta.exists' => 'La cuenta bancaria seleccionada no existe o no pertenece a su empresa.',
            'correlativo.required' => 'El correlativo es requerido.',
            'correlativo.integer' => 'El correlativo debe ser un número entero.',
            'correlativo.min' => 'El correlativo debe ser mayor a 0.',
            'anombrede.required' => 'El campo "a nombre de" es requerido.',
            'anombrede.max' => 'El campo "a nombre de" no puede exceder 255 caracteres.',
            'concepto.required' => 'El concepto es requerido.',
            'concepto.max' => 'El concepto no puede exceder 255 caracteres.',
            'total.required' => 'El total es requerido.',
            'total.numeric' => 'El total debe ser un número.',
            'total.min' => 'El total no puede ser negativo.',
            'id_usuario.required' => 'El usuario es requerido.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'id_empresa.required' => 'La empresa es requerida.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'estado.in' => 'El estado debe ser Pendiente, Aprobado o Anulado.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar strings
        if ($this->has('anombrede')) {
            $this->merge(['anombrede' => trim($this->anombrede)]);
        }

        if ($this->has('concepto')) {
            $this->merge(['concepto' => trim($this->concepto)]);
        }

        // Convertir valores numéricos
        if ($this->has('total')) {
            $this->merge(['total' => (float) $this->total]);
        }
    }
}

