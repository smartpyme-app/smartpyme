<?php

namespace App\Http\Requests\Licencias;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Licencias\Empresa as LicenciaEmpresa;

class StoreLicenciaEmpresaRequest extends FormRequest
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
            'id' => 'sometimes|nullable|integer|exists:licencia_empresas,id',
            'id_licencia' => [
                'required',
                'integer',
                'exists:licencias,id',
            ],
            'id_empresa' => [
                'required',
                'integer',
                'exists:empresas,id',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'id.exists' => 'La relación licencia-empresa no existe.',
            'id_licencia.required' => 'El ID de la licencia es obligatorio.',
            'id_licencia.integer' => 'El ID de la licencia debe ser un número entero.',
            'id_licencia.exists' => 'La licencia seleccionada no existe.',
            'id_empresa.required' => 'El ID de la empresa es obligatorio.',
            'id_empresa.integer' => 'El ID de la empresa debe ser un número entero.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'id_licencia' => 'licencia',
            'id_empresa' => 'empresa',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Solo validar duplicados si no es una actualización
            if (!$this->input('id')) {
                $existe = LicenciaEmpresa::where('id_empresa', $this->id_empresa)
                    ->where('id_licencia', $this->id_licencia)
                    ->first();

                if ($existe) {
                    $validator->errors()->add('id_empresa', 'Esta empresa ya ha sido agregada a esta licencia.');
                }
            }
        });
    }
}

