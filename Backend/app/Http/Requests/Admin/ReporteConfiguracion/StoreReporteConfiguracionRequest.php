<?php

namespace App\Http\Requests\Admin\ReporteConfiguracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReporteConfiguracionRequest extends FormRequest
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
            'nombre_reporte' => ['nullable', 'string'],
            'tipo_reporte' => ['required', 'string'],
            'frecuencia' => ['required', 'string', Rule::in(['diario', 'semanal', 'mensual'])],
            'destinatarios' => ['required', 'array', 'min:1'],
            'destinatarios.*' => ['required', 'email'],
            'sucursales' => ['nullable', 'array'],
            'sucursales.*' => ['integer', 'exists:sucursales,id'],
            'dias_semana' => ['nullable', 'array', 'required_if:frecuencia,semanal'],
            'dia_mes' => ['nullable', 'integer', 'min:1', 'max:31', 'required_if:frecuencia,mensual'],
            'envio_matutino' => ['nullable', 'boolean'],
            'envio_mediodia' => ['nullable', 'boolean'],
            'envio_nocturno' => ['nullable', 'boolean'],
            'asunto_correo' => ['nullable', 'string'],
            'activo' => ['nullable', 'boolean'],
            'id' => ['nullable', 'integer', 'exists:reporte_configuraciones,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'tipo_reporte.required' => 'El tipo de reporte es requerido.',
            'frecuencia.required' => 'La frecuencia es requerida.',
            'frecuencia.in' => 'La frecuencia debe ser diario, semanal o mensual.',
            'destinatarios.required' => 'Debe haber al menos un destinatario.',
            'destinatarios.array' => 'Los destinatarios deben ser un arreglo.',
            'destinatarios.min' => 'Debe haber al menos un destinatario.',
            'destinatarios.*.email' => 'Uno o más correos electrónicos no son válidos.',
            'sucursales.array' => 'Las sucursales deben ser un arreglo.',
            'sucursales.*.exists' => 'Una o más sucursales no existen.',
            'dias_semana.required_if' => 'Debe seleccionar al menos un día de la semana.',
            'dia_mes.required_if' => 'Debe seleccionar un día del mes.',
            'dia_mes.min' => 'El día del mes debe ser entre 1 y 31.',
            'dia_mes.max' => 'El día del mes debe ser entre 1 y 31.',
            'id.exists' => 'La configuración seleccionada no existe.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->frecuencia === 'semanal' && empty($this->dias_semana)) {
                $validator->errors()->add('dias_semana', 'Debe seleccionar al menos un día de la semana.');
            }

            if ($this->frecuencia === 'mensual' && !$this->dia_mes) {
                $validator->errors()->add('dia_mes', 'Debe seleccionar un día del mes.');
            }

            if (!$this->envio_matutino && !$this->envio_mediodia && !$this->envio_nocturno) {
                $validator->errors()->add('envio_matutino', 'Debe seleccionar al menos un horario de envío.');
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar strings
        if ($this->has('nombre_reporte')) {
            $this->merge(['nombre_reporte' => trim($this->nombre_reporte)]);
        }

        if ($this->has('asunto_correo')) {
            $this->merge(['asunto_correo' => trim($this->asunto_correo)]);
        }
    }
}

