<?php

namespace App\Http\Requests\Inventario\Productos;

use Illuminate\Foundation\Http\FormRequest;

class ImportarShopifyRequest extends FormRequest
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
            'shopify_store_url' => 'required|string|url|max:500',
            'shopify_consumer_secret' => 'required|string|max:255',
            'shopify_consumer_key' => 'sometimes|nullable|string|max:255',
            'id_empresa' => 'required|integer|exists:empresas,id',
            'id_usuario' => 'required|integer|exists:users,id',
            'id_sucursal' => 'sometimes|nullable|integer|exists:sucursales,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'shopify_store_url.required' => 'La URL de la tienda Shopify es obligatoria.',
            'shopify_store_url.url' => 'La URL de la tienda debe ser una URL válida.',
            'shopify_store_url.max' => 'La URL de la tienda no puede exceder 500 caracteres.',
            'shopify_consumer_secret.required' => 'La clave secreta de Shopify es obligatoria.',
            'shopify_consumer_secret.max' => 'La clave secreta no puede exceder 255 caracteres.',
            'shopify_consumer_key.max' => 'La clave de consumidor no puede exceder 255 caracteres.',
            'id_empresa.required' => 'La empresa es obligatoria.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'id_usuario.required' => 'El usuario es obligatorio.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'shopify_store_url' => 'URL de la tienda Shopify',
            'shopify_consumer_secret' => 'clave secreta de Shopify',
            'shopify_consumer_key' => 'clave de consumidor de Shopify',
            'id_empresa' => 'empresa',
            'id_usuario' => 'usuario',
            'id_sucursal' => 'sucursal',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validar que la URL sea de Shopify
            if ($this->has('shopify_store_url') && !preg_match('/\.myshopify\.com/', $this->shopify_store_url)) {
                $validator->errors()->add(
                    'shopify_store_url',
                    'La URL debe ser de una tienda Shopify (debe contener .myshopify.com).'
                );
            }
        });
    }
}

