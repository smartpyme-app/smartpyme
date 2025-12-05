<?php

namespace App\Http\Requests\Compras\Proveedores;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProveedorRequest extends FormRequest
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
            'id' => 'sometimes|nullable|integer|exists:proveedores,id',
            'nombre' => 'required_if:tipo,"Persona"|nullable|string|max:255',
            'apellido' => 'required_if:tipo,"Persona"|nullable|string|max:255',
            'nombre_empresa' => 'required_if:tipo,"Empresa"|nullable|string|max:255',
            'tipo' => 'required|string|max:255|in:Persona,Empresa',
            'id_empresa' => 'required|integer|exists:empresas,id',
            'ncr' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('proveedores', 'ncr')->ignore($id),
            ],
            'dui' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('proveedores', 'dui')->ignore($id),
            ],
            'nit' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('proveedores', 'nit')->ignore($id),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'El proveedor no existe.',
            'nombre.required_if' => 'El campo nombre es obligatorio.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'apellido.required_if' => 'El campo apellido es obligatorio para proveedores tipo Persona.',
            'apellido.max' => 'El apellido no puede exceder 255 caracteres.',
            'nombre_empresa.required_if' => 'El campo nombre_empresa es obligatorio.',
            'nombre_empresa.max' => 'El nombre de empresa no puede exceder 255 caracteres.',
            'tipo.required' => 'El tipo es obligatorio.',
            'tipo.max' => 'El tipo no puede exceder 255 caracteres.',
            'tipo.in' => 'El tipo debe ser Persona o Empresa.',
            'id_empresa.required' => 'La empresa es obligatoria.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'ncr.unique' => 'El NCR ya está registrado.',
            'ncr.max' => 'El NCR no puede exceder 255 caracteres.',
            'dui.unique' => 'El DUI ya está registrado.',
            'dui.max' => 'El DUI no puede exceder 255 caracteres.',
            'nit.unique' => 'El NIT ya está registrado.',
            'nit.max' => 'El NIT no puede exceder 255 caracteres.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'apellido' => 'apellido',
            'nombre_empresa' => 'nombre de empresa',
            'tipo' => 'tipo',
            'id_empresa' => 'empresa',
            'ncr' => 'NCR',
            'dui' => 'DUI',
            'nit' => 'NIT',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar strings
        if ($this->has('nombre') && $this->nombre) {
            $this->merge([
                'nombre' => trim($this->nombre),
            ]);
        }

        if ($this->has('apellido') && $this->apellido) {
            $this->merge([
                'apellido' => trim($this->apellido),
            ]);
        }

        if ($this->has('nombre_empresa') && $this->nombre_empresa) {
            $this->merge([
                'nombre_empresa' => trim($this->nombre_empresa),
            ]);
        }
    }
}

