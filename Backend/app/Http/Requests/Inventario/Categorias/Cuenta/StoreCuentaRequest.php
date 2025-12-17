<?php

namespace App\Http\Requests\Inventario\Categorias\Cuenta;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCuentaRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:categoria_sucursal_cuenta,id'],
            'id_categoria' => ['required', 'integer', 'exists:categorias,id'],
            'id_sucursal' => ['required', 'integer', 'exists:sucursales,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'La cuenta seleccionada no existe.',
            'id_categoria.required' => 'La categoría es requerida.',
            'id_categoria.exists' => 'La categoría seleccionada no existe.',
            'id_sucursal.required' => 'La sucursal es requerida.',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validar que no exista otra cuenta para la misma categoría y sucursal (solo si es nuevo)
            if (!$this->filled('id')) {
                $existe = \App\Models\Inventario\Categorias\Cuenta::where('id_categoria', $this->id_categoria)
                    ->where('id_sucursal', $this->id_sucursal)
                    ->exists();

                if ($existe) {
                    $validator->errors()->add('id_sucursal', 'Ya ha sido configurada una cuenta en esta sucursal para esta categoría.');
                }
            }
        });
    }
}

