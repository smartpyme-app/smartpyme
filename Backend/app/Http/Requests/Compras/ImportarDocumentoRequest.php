<?php

namespace App\Http\Requests\Compras;

use Illuminate\Foundation\Http\FormRequest;

class ImportarDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contenido' => 'required_without:archivo|string|max:5000000',
            'archivo' => 'required_without:contenido|file|max:5120|mimes:xml,json,txt',
            'json_data' => 'sometimes|string|max:5000000',
        ];
    }

    public function messages(): array
    {
        return [
            'contenido.required_without' => 'Debe enviar el contenido del documento o un archivo.',
            'archivo.required_without' => 'Debe enviar el contenido del documento o un archivo.',
            'archivo.max' => 'El archivo no puede superar 5 MB.',
        ];
    }

    /**
     * Contenido del documento (texto plano XML/JSON).
     */
    public function contenidoDocumento(): string
    {
        if ($this->hasFile('archivo')) {
            return (string) file_get_contents($this->file('archivo')->getRealPath());
        }

        $contenido = $this->input('contenido');
        if (is_string($contenido) && trim($contenido) !== '') {
            return $contenido;
        }

        $legacy = $this->input('json_data');
        if (is_string($legacy) && trim($legacy) !== '') {
            return $legacy;
        }

        return '';
    }
}
