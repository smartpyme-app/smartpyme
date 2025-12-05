<?php

namespace App\Http\Requests\Admin\Usuarios;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUsuarioRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // La autorización compleja se maneja en el controlador con checkAuth()
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $id = $this->input('id');
        $isUpdate = !empty($id);
        
        return [
            'id'            => 'sometimes|nullable|integer|exists:users,id',
            'name'          => 'required|max:255',
            'email'         => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($id),
            ],
            'tipo'          => 'required|string',
            'id_empresa'    => 'required|integer|exists:empresas,id',
            'id_sucursal'   => 'required|integer|exists:sucursales,id',
            'id_bodega'     => 'required|integer|exists:bodegas,id',
            'telefono'      => [
                'sometimes',
                'nullable',
                'string',
                Rule::unique('users', 'telefono')->ignore($id),
            ],
            'password'      => array_merge(
                $isUpdate ? ['nullable'] : ['required'],
                [
                    'confirmed',
                    'min:8',
                    'regex:/[a-z]/',
                    'regex:/[A-Z]/',
                    'regex:/[0-9]/',
                    'regex:/[!@#$%^&*()_+{}\[\]:;<>,.?~\\-]/',
                ]
            ),
            'rol_id'        => 'nullable|integer|exists:roles,id',
            'whatsapp_verified' => 'sometimes|boolean',
            'file'          => 'sometimes|file|image|mimes:jpeg,png,jpg,gif,svg|max:5120',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'El usuario no existe.',
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no puede exceder 255 caracteres.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico debe ser una dirección válida.',
            'email.unique' => 'Este correo electrónico ya está registrado.',
            'tipo.required' => 'El tipo de usuario es obligatorio.',
            'id_empresa.required' => 'La empresa es obligatoria.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'id_sucursal.required' => 'La sucursal es obligatoria.',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
            'id_bodega.required' => 'La bodega es obligatoria.',
            'id_bodega.exists' => 'La bodega seleccionada no existe.',
            'telefono.unique' => 'Este teléfono ya está registrado.',
            'password.required' => 'La contraseña es obligatoria al crear un nuevo usuario.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.regex' => 'La contraseña debe contener al menos una letra minúscula, una mayúscula, un número y un carácter especial.',
            'rol_id.exists' => 'El rol seleccionado no existe.',
            'whatsapp_verified.boolean' => 'El estado de verificación de WhatsApp debe ser verdadero o falso.',
            'file.file' => 'El archivo debe ser válido.',
            'file.image' => 'El archivo debe ser una imagen.',
            'file.mimes' => 'La imagen debe ser de tipo: jpeg, png, jpg, gif o svg.',
            'file.max' => 'La imagen no puede exceder 5MB.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'tipo' => 'tipo de usuario',
            'id_empresa' => 'empresa',
            'id_sucursal' => 'sucursal',
            'id_bodega' => 'bodega',
            'telefono' => 'teléfono',
            'password' => 'contraseña',
            'rol_id' => 'rol',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar email
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim($this->email)),
            ]);
        }

        // Convertir whatsapp_verified a boolean
        if ($this->has('whatsapp_verified')) {
            $this->merge([
                'whatsapp_verified' => filter_var($this->whatsapp_verified, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        // Sanitizar teléfono (remover caracteres no numéricos)
        if ($this->has('telefono') && $this->telefono) {
            $this->merge([
                'telefono' => preg_replace('/[^0-9]/', '', $this->telefono),
            ]);
        }
    }
}

