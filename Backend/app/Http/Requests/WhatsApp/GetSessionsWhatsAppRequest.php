<?php

namespace App\Http\Requests\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetSessionsWhatsAppRequest extends FormRequest
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
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'paginate' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', Rule::in(['connected', 'pending_code', 'pending_user', 'pending_verification', 'blocked', 'disconnected'])],
            'empresa_id' => ['nullable', 'integer', 'exists:empresas,id'],
            'id_empresa' => ['nullable', 'integer', 'exists:empresas,id'],
            'id_usuario' => ['nullable', 'integer', 'exists:users,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'buscador' => ['nullable', 'string', 'max:255'],
            'whatsapp_number' => ['nullable', 'string', 'max:255'],
            'inicio' => ['nullable', 'date'],
            'fin' => ['nullable', 'date', 'after_or_equal:inicio'],
            'con_mensajes' => ['nullable', 'boolean'],
            'activa' => ['nullable', 'boolean'],
            'orden' => ['nullable', 'string', Rule::in(['created_at', 'last_message_at', 'message_count', 'whatsapp_number', 'status'])],
            'direccion' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'per_page.integer' => 'El número de registros por página debe ser un entero.',
            'per_page.min' => 'El número de registros por página debe ser al menos 1.',
            'per_page.max' => 'El número de registros por página no puede exceder 200.',
            'paginate.integer' => 'El número de registros por página debe ser un entero.',
            'paginate.min' => 'El número de registros por página debe ser al menos 1.',
            'paginate.max' => 'El número de registros por página no puede exceder 200.',
            'page.integer' => 'El número de página debe ser un entero.',
            'page.min' => 'El número de página debe ser al menos 1.',
            'status.in' => 'El estado debe ser uno de los valores permitidos.',
            'empresa_id.integer' => 'El ID de empresa debe ser un número entero.',
            'empresa_id.exists' => 'La empresa seleccionada no existe.',
            'id_empresa.integer' => 'El ID de empresa debe ser un número entero.',
            'id_empresa.exists' => 'La empresa seleccionada no existe.',
            'id_usuario.integer' => 'El ID de usuario debe ser un número entero.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'search.max' => 'El término de búsqueda no puede exceder 255 caracteres.',
            'buscador.max' => 'El término de búsqueda no puede exceder 255 caracteres.',
            'whatsapp_number.max' => 'El número de WhatsApp no puede exceder 255 caracteres.',
            'inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'con_mensajes.boolean' => 'El campo con_mensajes debe ser verdadero o falso.',
            'activa.boolean' => 'El campo activa debe ser verdadero o falso.',
            'orden.in' => 'El campo de ordenamiento no es válido.',
            'direccion.in' => 'La dirección de ordenamiento debe ser "asc" o "desc".',
        ];
    }
}

