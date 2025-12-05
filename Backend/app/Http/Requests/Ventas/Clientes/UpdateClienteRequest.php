<?php

namespace App\Http\Requests\Ventas\Clientes;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClienteRequest extends FormRequest
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
            'id' => 'required|integer|exists:clientes,id',
            'nombre' => 'required_if:tipo,"Persona"|nullable|string|max:255',
            'apellido' => 'required_if:tipo,"Persona"|nullable|string|max:255',
            'nombre_empresa' => 'required_if:tipo,"Empresa"|nullable|string|max:255',
            'tipo' => 'required|string|in:Persona,Empresa',
            'id_empresa' => 'required|integer|exists:empresas,id',
            'contactos' => 'sometimes|array',
            'contactos.*.nombre' => 'sometimes|nullable|string|max:255',
            'contactos.*.name' => 'sometimes|nullable|string|max:255',
            'contactos.*.apellido' => 'sometimes|nullable|string|max:255',
            'contactos.*.lastname' => 'sometimes|nullable|string|max:255',
            'contactos.*.correo' => 'sometimes|nullable|email|max:255',
            'contactos.*.email' => 'sometimes|nullable|email|max:255',
            'contactos.*.telefono' => 'sometimes|nullable|string|max:255',
            'contactos.*.cargo' => 'sometimes|nullable|string|max:255',
            'contactos.*.sexo' => 'sometimes|nullable|string|max:255',
            'contactos.*.red_social' => 'sometimes|nullable|string|max:255',
            'contactos.*.fecha_nacimiento' => 'sometimes|nullable|date',
            'contactos.*.nota' => 'sometimes|nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.required' => 'El ID del cliente es obligatorio.',
            'id.exists' => 'El cliente no existe.',
            'nombre.required_if' => 'El campo nombre es obligatorio para clientes tipo Persona.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'apellido.required_if' => 'El campo apellido es obligatorio para clientes tipo Persona.',
            'apellido.max' => 'El apellido no puede exceder 255 caracteres.',
            'nombre_empresa.required_if' => 'El campo nombre de empresa es obligatorio para clientes tipo Empresa.',
            'nombre_empresa.max' => 'El nombre de empresa no puede exceder 255 caracteres.',
            'tipo.required' => 'El tipo de cliente es obligatorio.',
            'tipo.in' => 'El tipo debe ser Persona o Empresa.',
            'id_empresa.required' => 'La empresa es obligatoria.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'contactos.array' => 'Los contactos deben ser un array.',
            'contactos.*.correo.email' => 'El correo del contacto debe ser una dirección válida.',
            'contactos.*.email.email' => 'El correo del contacto debe ser una dirección válida.',
            'contactos.*.fecha_nacimiento.date' => 'La fecha de nacimiento del contacto debe ser una fecha válida.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'id' => 'cliente',
            'nombre' => 'nombre',
            'apellido' => 'apellido',
            'nombre_empresa' => 'nombre de empresa',
            'tipo' => 'tipo de cliente',
            'id_empresa' => 'empresa',
            'contactos' => 'contactos',
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

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validar que si es tipo Empresa y tiene contactos, los contactos sean válidos
            if ($this->tipo === 'Empresa' && $this->has('contactos') && is_array($this->contactos)) {
                foreach ($this->contactos as $index => $contacto) {
                    $nombre = $contacto['nombre'] ?? $contacto['name'] ?? null;
                    $correo = $contacto['correo'] ?? $contacto['email'] ?? null;
                    
                    if (empty($nombre) && empty($correo)) {
                        $validator->errors()->add(
                            "contactos.{$index}",
                            'El contacto debe tener al menos un nombre o un correo electrónico.'
                        );
                    }
                }
            }
        });
    }
}

