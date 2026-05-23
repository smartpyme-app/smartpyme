<?php

namespace App\Http\Requests\Contabilidad\LibrosIVA;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class BaseLibroIVARequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('id_sucursal') === '') {
            $this->merge(['id_sucursal' => null]);
        }

        if ((! $this->filled('inicio') || ! $this->filled('fin'))
            && $this->filled('anio')
            && $this->filled('mes')) {
            $base = Carbon::create((int) $this->input('anio'), (int) $this->input('mes'), 1)->startOfMonth();
            $this->merge([
                'inicio' => $base->format('Y-m-d'),
                'fin' => $base->copy()->endOfMonth()->format('Y-m-d'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'inicio' => ['required', 'date'],
            'fin' => ['required', 'date', 'after_or_equal:inicio'],
            'id_sucursal' => ['nullable', 'integer', 'exists:sucursales,id'],
            'formato' => ['nullable', 'string', 'in:json,pdf'],
        ];
    }

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
