<?php

namespace App\Http\Requests\External\Sales;

use Illuminate\Foundation\Http\FormRequest;

class IndexSalesRequest extends FormRequest
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
            'fecha_inicio' => ['nullable', 'date', 'date_format:Y-m-d'],
            'fecha_fin' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
            'estado' => ['nullable', 'string', 'in:Completada,Pendiente,Anulada,Cotizacion'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'order_by' => ['nullable', 'string', 'in:fecha,total,correlativo,created_at'],
            'order_direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_inicio.date_format' => 'La fecha de inicio debe tener el formato Y-m-d.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.date_format' => 'La fecha de fin debe tener el formato Y-m-d.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'estado.in' => 'El estado debe ser uno de: Completada, Pendiente, Anulada, Cotizacion.',
            'page.integer' => 'El número de página debe ser un número entero.',
            'page.min' => 'El número de página debe ser al menos 1.',
            'per_page.integer' => 'Los registros por página deben ser un número entero.',
            'per_page.min' => 'Los registros por página deben ser al menos 1.',
            'per_page.max' => 'Los registros por página no pueden exceder 200.',
            'order_by.in' => 'El campo de ordenamiento debe ser uno de: fecha, total, correlativo, created_at.',
            'order_direction.in' => 'La dirección del ordenamiento debe ser asc o desc.',
        ];
    }
}

