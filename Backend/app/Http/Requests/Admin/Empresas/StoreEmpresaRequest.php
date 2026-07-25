<?php

namespace App\Http\Requests\Admin\Empresas;

use App\Helpers\CountryTermsHelper;
use App\Models\Admin\Empresa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmpresaRequest extends FormRequest
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
        $id = $this->input('id');
        
        return [
            'id' => 'sometimes|nullable|integer|exists:empresas,id',
            'nombre' => 'required|string|max:255',
            'iva' => 'required|numeric|min:0|max:100',
            'file' => 'sometimes|file|image|mimes:jpeg,png,jpg,gif,svg|max:5120',
            'isRegister' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        $tax = $this->taxLabel();

        return [
            'id.exists' => 'La empresa no existe.',
            'nombre.required' => 'El nombre de la empresa es obligatorio.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'iva.required' => "El {$tax} es obligatorio.",
            'iva.numeric' => "El {$tax} debe ser un número.",
            'iva.min' => "El {$tax} no puede ser negativo.",
            'iva.max' => "El {$tax} no puede ser mayor a 100.",
            'file.file' => 'El archivo debe ser válido.',
            'file.image' => 'El archivo debe ser una imagen.',
            'file.mimes' => 'La imagen debe ser de tipo: jpeg, png, jpg, gif o svg.',
            'file.max' => 'La imagen no puede exceder 5MB.',
            'isRegister.boolean' => 'El campo isRegister debe ser verdadero o falso.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre de la empresa',
            'iva' => $this->taxLabel(),
            'file' => 'logo',
        ];
    }

    private function taxLabel(): string
    {
        return CountryTermsHelper::tax('taxLabel', new Empresa([
            'pais' => $this->input('pais'),
            'cod_pais' => $this->input('cod_pais'),
        ]));
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar nombre
        if ($this->has('nombre')) {
            $this->merge([
                'nombre' => trim($this->nombre),
            ]);
        }

        // Asegurar que iva sea numérico
        if ($this->has('iva')) {
            $this->merge([
                'iva' => (float) $this->iva,
            ]);
        }
    }
}

