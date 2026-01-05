<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUsuarioRequest extends FormRequest
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
        $isUpdate = !empty($id);
        
        return [
            'id' => 'sometimes|nullable|integer|exists:users,id',
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($id),
            ],
            'rol_id' => 'required|integer|exists:roles,id',
            'id_empresa' => 'required|integer|exists:empresas,id',
            'id_bodega' => 'required|integer|exists:sucursal_bodegas,id',
            'id_sucursal' => 'required|integer|exists:sucursales,id',
            'password' => array_merge(
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
            'file' => 'sometimes|file|image|mimes:jpeg,png,jpg,gif,svg|max:5120',
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
            'rol_id.required' => 'El rol es obligatorio.',
            'rol_id.exists' => 'El rol seleccionado no existe.',
            'id_empresa.required' => 'La empresa es obligatoria.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'id_bodega.required' => 'La bodega es obligatoria.',
            'id_bodega.exists' => 'La bodega seleccionada no existe.',
            'id_sucursal.required' => 'La sucursal es obligatoria.',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
            'password.required' => 'La contraseña es obligatoria al crear un nuevo usuario.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.regex' => 'La contraseña debe contener al menos una letra minúscula, una mayúscula, un número y un carácter especial.',
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
            'rol_id' => 'rol',
            'id_empresa' => 'empresa',
            'id_bodega' => 'bodega',
            'id_sucursal' => 'sucursal',
            'password' => 'contraseña',
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

        // Sanitizar nombre
        if ($this->has('name')) {
            $this->merge([
                'name' => trim($this->name),
            ]);
        }
    }
}

