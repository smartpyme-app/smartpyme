<?php

namespace App\Http\Requests\Ventas\Clientes;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentoRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:cliente_documentos,id'],
            'nombre' => ['nullable', 'string', 'max:255'],
            'file' => ['required_without:url', 'file', 'mimes:jpeg,png,jpg,ppt,pptx,doc,docx,pdf,xls,xlsx', 'max:3000'],
            'url' => ['nullable', 'string', 'url', 'max:255'],
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'El documento seleccionado no existe.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'file.required_without' => 'El archivo es requerido si no se proporciona una URL.',
            'file.file' => 'El archivo debe ser un archivo válido.',
            'file.mimes' => 'El archivo debe ser de tipo: jpeg, png, jpg, ppt, pptx, doc, docx, pdf, xls, xlsx.',
            'file.max' => 'El tamaño del archivo no debe exceder los 3MB.',
            'url.url' => 'La URL debe ser una URL válida.',
            'url.max' => 'La URL no puede exceder 255 caracteres.',
            'cliente_id.required' => 'El cliente es requerido.',
            'cliente_id.exists' => 'El cliente seleccionado no existe.',
        ];
    }
}

