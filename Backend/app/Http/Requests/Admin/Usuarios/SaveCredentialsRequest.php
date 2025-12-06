<?php

namespace App\Http\Requests\Admin\Usuarios;

use Illuminate\Foundation\Http\FormRequest;

class SaveCredentialsRequest extends FormRequest
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
        $rules = [
            'tipo' => 'required|in:woocommerce,shopify',
            'canal_id' => 'required|numeric|exists:canales,id',
        ];

        if ($this->tipo === 'woocommerce') {
            $rules['store_url'] = 'required|url';
            $rules['consumer_key'] = 'required|string';
            $rules['consumer_secret'] = 'required|string';
        } else { // shopify
            $rules['store_url'] = 'required|string';
            $rules['consumer_secret'] = 'required|string';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'tipo.required' => 'El tipo de integración es obligatorio.',
            'tipo.in' => 'El tipo debe ser woocommerce o shopify.',
            'canal_id.required' => 'El Canal es obligatorio.',
            'canal_id.numeric' => 'El Canal debe ser numérico.',
            'canal_id.exists' => 'El Canal seleccionado no existe.',
            'store_url.required' => 'La URL de la tienda es obligatoria.',
            'store_url.url' => 'La URL de la tienda debe ser una dirección válida.',
            'consumer_key.required' => 'La Consumer Key es obligatoria.',
            'consumer_key.string' => 'La Consumer Key debe ser una cadena de texto.',
            'consumer_secret.required' => 'El Consumer Secret es obligatorio.',
            'consumer_secret.string' => 'El Consumer Secret debe ser una cadena de texto.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'tipo' => 'tipo de integración',
            'canal_id' => 'canal',
            'store_url' => 'URL de la tienda',
            'consumer_key' => 'Consumer Key',
            'consumer_secret' => 'Consumer Secret',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar URL de la tienda
        if ($this->has('store_url')) {
            $storeUrl = trim($this->store_url);
            // Asegurar que la URL tenga protocolo
            if ($this->tipo === 'woocommerce' && !preg_match('/^https?:\/\//', $storeUrl)) {
                $storeUrl = 'https://' . $storeUrl;
            }
            $this->merge([
                'store_url' => rtrim($storeUrl, '/'),
            ]);
        }
    }
}

