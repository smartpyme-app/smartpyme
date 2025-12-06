<?php

namespace App\Http\Requests\Contabilidad\Catalogo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCuentaRequest extends FormRequest
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
        $empresa_id = $this->id_empresa ?? auth()->user()->id_empresa;

        return [
            'id' => ['nullable', 'integer', 'exists:catalogo_cuentas,id'],
            'codigo' => [
                'required',
                'string',
                'max:50',
                Rule::unique('catalogo_cuentas', 'codigo')
                    ->ignore($this->id)
                    ->where('id_empresa', $empresa_id)
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'naturaleza' => ['required', 'string', 'in:Deudor,Acreedor'],
            'id_cuenta_padre' => ['nullable'],
            'rubro' => ['required', 'string', 'max:255'],
            'nivel' => ['required', 'integer', 'min:0', 'max:10'],
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'codigo.required' => 'El código es obligatorio.',
            'codigo.unique' => 'Ya existe una cuenta con este código en la empresa.',
            'codigo.max' => 'El código no puede exceder 50 caracteres.',
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'naturaleza.required' => 'La naturaleza es obligatoria.',
            'naturaleza.in' => 'La naturaleza debe ser Deudor o Acreedor.',
            'rubro.required' => 'El rubro es obligatorio.',
            'rubro.max' => 'El rubro no puede exceder 255 caracteres.',
            'nivel.required' => 'El nivel es obligatorio.',
            'nivel.integer' => 'El nivel debe ser un número entero.',
            'nivel.min' => 'El nivel no puede ser menor a 0.',
            'nivel.max' => 'El nivel no puede exceder 10.',
            'id_empresa.required' => 'La empresa es obligatoria.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'id.exists' => 'La cuenta seleccionada no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Asegurar que id_empresa esté presente
        if (!$this->has('id_empresa')) {
            $this->merge(['id_empresa' => auth()->user()->id_empresa]);
        }

        // Limpiar strings
        if ($this->has('codigo')) {
            $this->merge(['codigo' => trim($this->codigo)]);
        }

        if ($this->has('nombre')) {
            $this->merge(['nombre' => trim($this->nombre)]);
        }

        if ($this->has('rubro')) {
            $this->merge(['rubro' => trim($this->rubro)]);
        }

        // Convertir nivel a entero
        if ($this->has('nivel')) {
            $this->merge(['nivel' => (int) $this->nivel]);
        }
    }
}

