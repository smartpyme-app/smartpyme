<?php

namespace App\Http\Requests\Bancos;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransaccionRequest extends FormRequest
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
            'id' => ['sometimes', 'nullable', 'integer', 'exists:cuentas_bancarias_transacciones,id'],
            'fecha' => ['required', 'date'],
            'id_cuenta' => ['required', 'integer', 'exists:cuentas_bancarias,id'],
            'concepto' => ['required', 'string', 'max:255'],
            'tipo_operacion' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'string', 'max:255', 'in:Cargo,Abono'],
            'estado' => ['required', 'string', 'max:255', 'in:Pendiente,Aprobada,Anulada'],
            'total' => ['required', 'numeric', 'min:0'],
            'id_usuario' => ['required', 'integer', 'exists:users,id'],
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:5120'],
            'url_referencia' => ['nullable', 'string', 'max:255'],
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
            'id_cuenta.required' => 'La cuenta bancaria es requerida.',
            'id_cuenta.exists' => 'La cuenta bancaria seleccionada no existe.',
            'concepto.required' => 'El concepto es requerido.',
            'concepto.max' => 'El concepto no puede exceder 255 caracteres.',
            'tipo_operacion.required' => 'El tipo de operación es requerido.',
            'tipo.required' => 'El tipo es requerido.',
            'tipo.in' => 'El tipo debe ser Cargo o Abono.',
            'estado.required' => 'El estado es requerido.',
            'estado.in' => 'El estado debe ser Pendiente, Aprobada o Anulada.',
            'total.required' => 'El total es requerido.',
            'total.numeric' => 'El total debe ser un número.',
            'total.min' => 'El total no puede ser negativo.',
            'id_usuario.required' => 'El usuario es requerido.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'id_empresa.required' => 'La empresa es requerida.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'file.mimes' => 'El archivo debe ser: pdf, doc, docx, jpg, jpeg o png.',
            'file.max' => 'El archivo no puede exceder 5MB.',
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

        // Normalizar valores decimales
        if ($this->has('total')) {
            $total = str_replace(',', '.', (string)$this->total);
            $this->merge(['total' => (float) $total]);
        }
    }
}

