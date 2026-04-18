<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class SubirCertificadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->empresa !== null;
    }

    protected function prepareForValidation(): void
    {
        $empresa = $this->user()?->empresa;
        $raw = (string) ($empresa?->nit ?? '');
        $digits = preg_replace('/\D+/', '', $raw);

        $this->merge([
            'nit' => $digits,
        ]);
    }

    public function rules(): array
    {
        return [
            'archivo' => [
                'required',
                File::default()
                    ->extensions(['pdf', 'crt', 'pem', 'p12', 'cer'])
                    ->max(5120),
            ],
            'nit' => ['required', 'string', 'regex:/^\d{9,14}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.required' => 'Debe seleccionar un archivo de certificado.',
            'archivo.max' => 'El archivo no puede superar 5 MB.',
            'nit.required' => 'La empresa no tiene un NIT registrado. Actualice los datos de la empresa antes de subir el certificado.',
            'nit.regex' => 'El NIT de la empresa no tiene un formato válido para El Salvador (solo dígitos, entre 9 y 14).',
        ];
    }
}
