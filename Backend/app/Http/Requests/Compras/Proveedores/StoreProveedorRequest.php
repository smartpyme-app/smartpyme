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
            ],
            'dui' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'nit' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'id_cuenta_contable' => 'sometimes|nullable|integer|exists:catalogo_cuentas,id',
            ...self::reglasDatosBancarios(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function reglasDatosBancarios(): array
    {
        return [
            'banco' => 'nullable|string|max:255|required_with:tipo_cuenta,numero_cuenta,titular_cuenta,forma_pago',
            'tipo_cuenta' => 'nullable|in:Ahorro,Corriente|required_with:banco,numero_cuenta,titular_cuenta,forma_pago',
            'numero_cuenta' => 'nullable|string|max:50|required_with:banco,tipo_cuenta,titular_cuenta,forma_pago',
            'titular_cuenta' => 'nullable|string|max:255|required_with:banco,tipo_cuenta,numero_cuenta,forma_pago',
            'forma_pago' => 'nullable|in:Transferencia,Cheque,Efectivo',
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
            'banco.required_with' => 'El banco es obligatorio cuando se registra una cuenta.',
            'tipo_cuenta.required_with' => 'El tipo de cuenta es obligatorio cuando se registra una cuenta.',
            'tipo_cuenta.in' => 'El tipo de cuenta debe ser Ahorro o Corriente.',
            'numero_cuenta.required_with' => 'El número de cuenta es obligatorio cuando se registra una cuenta.',
            'titular_cuenta.required_with' => 'El titular de la cuenta es obligatorio cuando se registra una cuenta.',
            'forma_pago.in' => 'La forma de pago debe ser Transferencia, Cheque o Efectivo.',
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
            'banco' => 'banco',
            'tipo_cuenta' => 'tipo de cuenta',
            'numero_cuenta' => 'número de cuenta',
            'titular_cuenta' => 'titular de la cuenta',
            'forma_pago' => 'forma de pago',
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

        foreach (['banco', 'tipo_cuenta', 'numero_cuenta', 'titular_cuenta', 'forma_pago'] as $campo) {
            if ($this->has($campo) && is_string($this->input($campo))) {
                $this->merge([$campo => trim($this->input($campo))]);
            }
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $id = $this->input('id');
            $idEmpresa = $this->input('id_empresa') ?? (auth()->check() ? auth()->user()->id_empresa : null);
            
            if (!$idEmpresa) {
                return;
            }
            
            // Validar unicidad de NCR solo si tiene valor
            if ($this->filled('ncr') && trim($this->input('ncr')) !== '') {
                $query = \App\Models\Compras\Proveedores\Proveedor::where('ncr', $this->input('ncr'))
                    ->where('id_empresa', $idEmpresa);
                
                if ($id) {
                    $query->where('id', '!=', $id);
                }
                
                if ($query->exists()) {
                    $validator->errors()->add('ncr', 'El NCR ya está registrado.');
                }
            }
            
            // Validar unicidad de DUI solo si tiene valor
            if ($this->filled('dui') && trim($this->input('dui')) !== '') {
                $query = \App\Models\Compras\Proveedores\Proveedor::where('dui', $this->input('dui'))
                    ->where('id_empresa', $idEmpresa);
                
                if ($id) {
                    $query->where('id', '!=', $id);
                }
                
                if ($query->exists()) {
                    $validator->errors()->add('dui', 'El DUI ya está registrado.');
                }
            }
            
            // Validar unicidad de NIT solo si tiene valor
            if ($this->filled('nit') && trim($this->input('nit')) !== '') {
                $query = \App\Models\Compras\Proveedores\Proveedor::where('nit', $this->input('nit'))
                    ->where('id_empresa', $idEmpresa);
                
                if ($id) {
                    $query->where('id', '!=', $id);
                }
                
                if ($query->exists()) {
                    $validator->errors()->add('nit', 'El NIT ya está registrado.');
                }
            }
        });
    }
}

