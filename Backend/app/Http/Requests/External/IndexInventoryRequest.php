<?php

namespace App\Http\Requests\External;

use Illuminate\Foundation\Http\FormRequest;

class IndexInventoryRequest extends FormRequest
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
            'codigo' => ['nullable', 'string'],
            'nombre' => ['nullable', 'string'],
            'categoria' => ['nullable', 'string'],
            'marca' => ['nullable', 'string'],
            'tipo' => ['nullable', 'string', 'in:Producto,Servicio'],
            'enable' => ['nullable', 'string', 'in:0,1'],
            'con_stock' => ['nullable', 'boolean'],
            'stock_minimo' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'order_by' => ['nullable', 'string', 'in:nombre,codigo,precio,costo,created_at'],
            'order_direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'tipo.in' => 'El tipo debe ser Producto o Servicio.',
            'enable.in' => 'El estado debe ser 0 o 1.',
            'con_stock.boolean' => 'El campo con stock debe ser verdadero o falso.',
            'stock_minimo.boolean' => 'El campo stock mínimo debe ser verdadero o falso.',
            'page.integer' => 'El número de página debe ser un número entero.',
            'page.min' => 'El número de página debe ser al menos 1.',
            'per_page.integer' => 'Los registros por página deben ser un número entero.',
            'per_page.min' => 'Los registros por página deben ser al menos 1.',
            'per_page.max' => 'Los registros por página no pueden exceder 200.',
            'order_by.in' => 'El campo de ordenamiento debe ser uno de: nombre, codigo, precio, costo, created_at.',
            'order_direction.in' => 'La dirección de ordenamiento debe ser asc o desc.',
        ];
    }
}

