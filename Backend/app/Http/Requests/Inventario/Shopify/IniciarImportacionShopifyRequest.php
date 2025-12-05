<?php

namespace App\Http\Requests\Inventario\Shopify;

use Illuminate\Foundation\Http\FormRequest;

class IniciarImportacionShopifyRequest extends FormRequest
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
            'shopify_store_url' => ['required', 'string', 'url', 'max:255'],
            'shopify_consumer_secret' => ['required', 'string', 'max:255'],
            'shopify_consumer_key' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'shopify_store_url.required' => 'La URL de la tienda Shopify es requerida.',
            'shopify_store_url.url' => 'La URL de la tienda debe ser una URL válida.',
            'shopify_store_url.max' => 'La URL de la tienda no puede exceder 255 caracteres.',
            'shopify_consumer_secret.required' => 'El Consumer Secret es requerido.',
            'shopify_consumer_secret.max' => 'El Consumer Secret no puede exceder 255 caracteres.',
            'shopify_consumer_key.max' => 'El Consumer Key no puede exceder 255 caracteres.',
        ];
    }
}

