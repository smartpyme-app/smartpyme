<?php

namespace App\Http\Requests\Contabilidad\LibrosIVA;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class BaseLibroIVARequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza filtros del front (mes/año) y valores vacíos antes de validar.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->input('id_sucursal') === '' || $this->input('id_sucursal') === null) {
            $merge['id_sucursal'] = null;
        }

        $inicio = $this->input('inicio');
        $fin = $this->input('fin');

        if ((! $this->esFechaLibroValida($inicio) || ! $this->esFechaLibroValida($fin))
            && $this->filled('anio')
            && $this->filled('mes')) {
            $anio = (int) $this->input('anio');
            $mes = (int) $this->input('mes');

            if ($anio >= 1970 && $mes >= 1 && $mes <= 12) {
                $inicioCarbon = Carbon::create($anio, $mes, 1)->startOfMonth();
                $merge['inicio'] = $inicioCarbon->format('Y-m-d');
                $merge['fin'] = $inicioCarbon->copy()->endOfMonth()->format('Y-m-d');
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    private function esFechaLibroValida(mixed $value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        try {
            Carbon::createFromFormat('Y-m-d', $value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'anio' => ['nullable', 'integer', 'min:1970', 'max:2100'],
            'mes' => ['nullable', 'integer', 'min:1', 'max:12'],
            'inicio' => ['required', 'date', 'date_format:Y-m-d'],
            'fin' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:inicio'],
            'id_sucursal' => ['nullable', 'integer', 'exists:sucursales,id'],
            'formato' => ['nullable', 'string', 'in:json,pdf'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'inicio.required' => 'La fecha de inicio es requerida.',
            'inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fin.required' => 'La fecha de fin es requerida.',
            'fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'id_sucursal.integer' => 'El ID de sucursal debe ser un número entero.',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
            'formato.in' => 'El formato debe ser json o pdf.',
        ];
    }
}

