<?php

namespace App\Http\Requests\Contabilidad\Partidas;

use Illuminate\Foundation\Http\FormRequest;

class AbrirPartidaRequest extends FormRequest
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
            'id' => ['required', 'integer', 'exists:partidas,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.required' => 'El ID de la partida es requerido.',
            'id.exists' => 'La partida seleccionada no existe.',
        ];
    }
}

