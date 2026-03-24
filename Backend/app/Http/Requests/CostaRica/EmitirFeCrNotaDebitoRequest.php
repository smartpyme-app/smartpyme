<?php

namespace App\Http\Requests\CostaRica;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EmitirFeCrNotaDebitoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $idEmpresa = Auth::user()?->id_empresa;

        return [
            'id' => [
                'required',
                'integer',
                Rule::exists('ventas', 'id')->where(fn ($q) => $q->where('id_empresa', $idEmpresa)),
            ],
            'motivo' => ['nullable', 'string', 'max:500'],
            'monto_linea' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
