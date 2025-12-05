<?php

namespace App\Http\Requests\Planilla;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmpleadoRequest extends FormRequest
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
            'nombres' => ['sometimes', 'string', 'max:100'],
            'apellidos' => ['sometimes', 'string', 'max:100'],
            'dui' => ['sometimes', 'nullable', 'string'],
            'nit' => ['nullable', 'string'],
            'isss' => ['nullable', 'string'],
            'afp' => ['nullable', 'string'],
            'fecha_nacimiento' => ['sometimes', 'date'],
            'direccion' => ['nullable', 'string'],
            'telefono' => ['nullable', 'string'],
            'email' => ['sometimes', 'email'],
            'salario_base' => ['sometimes', 'numeric', 'min:0'],
            'tipo_contrato' => ['sometimes'],
            'tipo_jornada' => ['sometimes'],
            'fecha_ingreso' => ['sometimes', 'date'],
            'id_departamento' => ['sometimes', 'integer', 'exists:departamentos_empresa,id'],
            'id_cargo' => ['sometimes', 'integer', 'exists:cargos_de_empresa,id'],
            'forma_pago' => ['nullable', 'string', 'in:Transferencia,Cheque,Efectivo'],
            'banco' => ['nullable', 'string', 'max:100'],
            'tipo_cuenta' => ['nullable', 'string', 'in:Ahorro,Corriente'],
            'numero_cuenta' => ['nullable', 'string', 'max:50'],
            'titular_cuenta' => ['nullable', 'string', 'max:100'],
            'estado' => ['sometimes'],
            'contacto_emergencia' => ['nullable', 'array'],
            'contacto_emergencia.nombre' => ['nullable', 'string'],
            'contacto_emergencia.relacion' => ['nullable', 'string'],
            'contacto_emergencia.telefono' => ['nullable', 'string'],
            'contacto_emergencia.direccion' => ['nullable', 'string'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validar DUI solo si viene y es diferente al actual
            if ($this->has('dui') && $this->dui !== null && trim($this->dui) !== '') {
                $empleadoId = $this->route('id');
                $empleado = \App\Models\Planilla\Empleado::find($empleadoId);
                
                if ($empleado) {
                    $duiActual = trim($empleado->dui ?? '');
                    $duiNuevo = trim($this->dui);
                    
                    if ($duiNuevo !== $duiActual) {
                        // Si el DUI cambió, validar unicidad
                        $existe = \App\Models\Planilla\Empleado::where('dui', $duiNuevo)
                            ->where('id', '!=', $empleadoId)
                            ->exists();
                        
                        if ($existe) {
                            $validator->errors()->add('dui', 'El DUI ya está registrado.');
                        }
                    }
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nombres.max' => 'Los nombres no pueden exceder 100 caracteres.',
            'apellidos.max' => 'Los apellidos no pueden exceder 100 caracteres.',
            'dui.unique' => 'El DUI ya está registrado.',
            'fecha_nacimiento.date' => 'La fecha de nacimiento debe ser una fecha válida.',
            'email.email' => 'El correo electrónico debe ser válido.',
            'salario_base.numeric' => 'El salario base debe ser un número.',
            'salario_base.min' => 'El salario base no puede ser negativo.',
            'fecha_ingreso.date' => 'La fecha de ingreso debe ser una fecha válida.',
            'id_departamento.exists' => 'El departamento seleccionado no existe.',
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

