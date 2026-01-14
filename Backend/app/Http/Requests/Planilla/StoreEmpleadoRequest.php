<?php

namespace App\Http\Requests\Planilla;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmpleadoRequest extends FormRequest
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
            'id' => ['sometimes', 'nullable', 'integer', 'exists:empleados,id'],
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'dui' => ['required', 'string', Rule::unique('empleados', 'dui')->ignore($this->id)],
            'nit' => ['nullable', 'string'],
            'isss' => ['nullable', 'string'],
            'afp' => ['nullable', 'string'],
            'fecha_nacimiento' => ['required', 'date'],
            'direccion' => ['nullable', 'string'],
            'telefono' => ['nullable', 'string'],
            'email' => ['required', 'email'],
            'salario_base' => ['required', 'numeric', 'min:0'],
            'tipo_contrato' => ['required'],
            'tipo_jornada' => ['required'],
            'fecha_ingreso' => ['required', 'date'],
            'id_departamento' => ['required', 'integer', 'exists:departamentos_empresa,id'],
            'id_cargo' => ['required', 'integer', 'exists:cargos_de_empresa,id'],
            'forma_pago' => ['nullable', 'string', 'in:Transferencia,Cheque,Efectivo', 'max:50'],
            'banco' => ['nullable', 'string', 'max:100'],
            'tipo_cuenta' => ['nullable', 'string', 'in:Ahorro,Corriente'],
            'numero_cuenta' => ['nullable', 'string', 'max:50'],
            'titular_cuenta' => ['nullable', 'string', 'max:100'],
            'contacto_emergencia' => ['nullable', 'array'],
            'contacto_emergencia.nombre' => ['nullable', 'string'],
            'contacto_emergencia.relacion' => ['nullable', 'string'],
            'contacto_emergencia.telefono' => ['nullable', 'string'],
            'contacto_emergencia.direccion' => ['nullable', 'string'],
            'configuracion_descuentos' => ['nullable', 'array'],
            'configuracion_descuentos.aplicar_afp' => ['nullable', 'boolean'],
            'configuracion_descuentos.aplicar_isss' => ['nullable', 'boolean']
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nombres.required' => 'Los nombres son requeridos.',
            'nombres.max' => 'Los nombres no pueden exceder 100 caracteres.',
            'apellidos.required' => 'Los apellidos son requeridos.',
            'apellidos.max' => 'Los apellidos no pueden exceder 100 caracteres.',
            'dui.required' => 'El DUI es requerido.',
            'dui.unique' => 'El DUI ya está registrado.',
            'fecha_nacimiento.required' => 'La fecha de nacimiento es requerida.',
            'fecha_nacimiento.date' => 'La fecha de nacimiento debe ser una fecha válida.',
            'email.required' => 'El correo electrónico es requerido.',
            'email.email' => 'El correo electrónico debe ser válido.',
            'salario_base.required' => 'El salario base es requerido.',
            'salario_base.numeric' => 'El salario base debe ser un número.',
            'salario_base.min' => 'El salario base no puede ser negativo.',
            'tipo_contrato.required' => 'El tipo de contrato es requerido.',
            'tipo_jornada.required' => 'El tipo de jornada es requerido.',
            'fecha_ingreso.required' => 'La fecha de ingreso es requerida.',
            'fecha_ingreso.date' => 'La fecha de ingreso debe ser una fecha válida.',
            'id_departamento.required' => 'El departamento es requerido.',
            'id_departamento.exists' => 'El departamento seleccionado no existe.',
            'id_cargo.required' => 'El cargo es requerido.',
            'id_cargo.exists' => 'El cargo seleccionado no existe.',
            'forma_pago.in' => 'La forma de pago debe ser: Transferencia, Cheque o Efectivo.',
            'tipo_cuenta.in' => 'El tipo de cuenta debe ser: Ahorro o Corriente.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar strings
        if ($this->has('nombres')) {
            $this->merge(['nombres' => trim($this->nombres)]);
        }

        if ($this->has('apellidos')) {
            $this->merge(['apellidos' => trim($this->apellidos)]);
        }

        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim($this->email))]);
        }

        // Convertir valores numéricos
        if ($this->has('salario_base')) {
            $this->merge(['salario_base' => (float) $this->salario_base]);
        }
    }
}

