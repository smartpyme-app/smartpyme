<?php

namespace App\Http\Requests\Inventario\Imagenes;

use Illuminate\Foundation\Http\FormRequest;

class StoreImagenRequest extends FormRequest
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
            'id' => ['nullable', 'integer', 'exists:imagenes,id'],
            'file' => ['required_without:img', 'image', 'mimes:jpeg,png,jpg', 'max:2000'],
            'img' => ['sometimes', 'string', 'max:255'],
            'id_producto' => ['required', 'integer', 'exists:productos,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'La imagen seleccionada no existe.',
            'file.required_without' => 'El archivo es requerido cuando no se proporciona una URL de imagen.',
            'file.image' => 'El archivo debe ser una imagen.',
            'file.mimes' => 'El archivo debe ser de tipo: jpeg, png, jpg.',
            'file.max' => 'El archivo no puede exceder 2000 kilobytes.',
            'img.max' => 'La URL de imagen no puede exceder 255 caracteres.',
            'id_producto.required' => 'El producto es requerido.',
            'id_producto.exists' => 'El producto seleccionado no existe.',
        ];
    }
}

