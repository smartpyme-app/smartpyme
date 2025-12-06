<?php

namespace App\Http\Requests\Admin\Documentos;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentoRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:255'],
            'correlativo' => ['required', 'string', 'max:255'],
            'id_empresa' => ['required', 'integer', 'exists:empresas,id'],
            'id_sucursal' => ['required', 'integer', 'exists:sucursales,id'],
            'id' => ['nullable', 'integer', 'exists:documentos,id'],
            'rangos' => ['nullable', 'string', 'max:255'],
            'numero_autorizacion' => ['nullable', 'string', 'max:255'],
            'resolucion' => ['nullable', 'string', 'max:255'],
            'nota' => ['nullable', 'string', 'max:500'],
            'nuevaResolucion' => ['nullable', 'boolean'],
            'predeterminado' => ['nullable', 'boolean'],
            'prefijo' => ['nullable', 'string'],
            'inicial' => ['nullable', 'string'],
            'final' => ['nullable', 'string'],
            'fecha' => ['nullable', 'date'],
            'caja_id' => ['nullable', 'integer', 'exists:cajas,id'],
            'change' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es requerido.',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres.',
            'correlativo.required' => 'El correlativo es requerido.',
            'correlativo.max' => 'El correlativo no puede exceder 255 caracteres.',
            'id_empresa.required' => 'La empresa es requerida.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'id_sucursal.required' => 'La sucursal es requerida.',
            'id_sucursal.exists' => 'La sucursal seleccionada no existe.',
            'id.exists' => 'El documento seleccionado no existe.',
            'rangos.max' => 'Los rangos no pueden exceder 255 caracteres.',
            'numero_autorizacion.max' => 'El número de autorización no puede exceder 255 caracteres.',
            'resolucion.max' => 'La resolución no puede exceder 255 caracteres.',
            'nota.max' => 'La nota no puede exceder 500 caracteres.',
            'fecha.date' => 'La fecha debe ser una fecha válida.',
            'caja_id.exists' => 'La caja seleccionada no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar strings
        if ($this->has('nombre')) {
            $this->merge(['nombre' => trim($this->nombre)]);
        }

        if ($this->has('correlativo')) {
            $this->merge(['correlativo' => trim($this->correlativo)]);
        }

        if ($this->has('rangos')) {
            $this->merge(['rangos' => trim($this->rangos)]);
        }

        if ($this->has('numero_autorizacion')) {
            $this->merge(['numero_autorizacion' => trim($this->numero_autorizacion)]);
        }

        if ($this->has('resolucion')) {
            $this->merge(['resolucion' => trim($this->resolucion)]);
        }

        if ($this->has('nota')) {
            $this->merge(['nota' => trim($this->nota)]);
        }
    }
}

