<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class BedrockChatRequest extends FormRequest
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
            'prompt' => ['required', 'string'],
            'history' => ['nullable', 'array'],
            'conversationId' => ['nullable', 'integer', 'exists:conversations,id'],
            'maxTokens' => ['nullable', 'integer', 'min:1', 'max:4000'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'topP' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'topK' => ['nullable', 'integer', 'min:0'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'modelType' => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'prompt.required' => 'El prompt es requerido.',
            'prompt.string' => 'El prompt debe ser una cadena de texto.',
            'history.array' => 'El historial debe ser un arreglo.',
            'conversationId.integer' => 'El ID de conversación debe ser un número entero.',
            'conversationId.exists' => 'La conversación seleccionada no existe.',
            'maxTokens.integer' => 'El máximo de tokens debe ser un número entero.',
            'maxTokens.min' => 'El máximo de tokens debe ser al menos 1.',
            'maxTokens.max' => 'El máximo de tokens no puede exceder 4000.',
            'temperature.numeric' => 'La temperatura debe ser un número.',
            'temperature.min' => 'La temperatura no puede ser menor a 0.',
            'temperature.max' => 'La temperatura no puede ser mayor a 1.',
            'topP.numeric' => 'El topP debe ser un número.',
            'topP.min' => 'El topP no puede ser menor a 0.',
            'topP.max' => 'El topP no puede ser mayor a 1.',
            'topK.integer' => 'El topK debe ser un número entero.',
            'topK.min' => 'El topK no puede ser menor a 0.',
            'user_id.integer' => 'El ID de usuario debe ser un número entero.',
            'user_id.exists' => 'El usuario seleccionado no existe.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitizar strings
        if ($this->has('prompt')) {
            $this->merge(['prompt' => trim($this->prompt)]);
        }
    }
}

