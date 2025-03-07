<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AWS Bedrock Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración para la integración con AWS Bedrock
    |
    */

    // Configuración AWS general
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_BEDROCK_REGION', 'us-east-2'),
    'default_model' => env('AWS_BEDROCK_DEFAULT_MODEL', 'haiku'), // Modelo predeterminado a utilizar
    
    // Claude 3.5 Haiku - Configuración
    'model_id_haiku' => 'anthropic.claude-3-5-haiku-20241022-v1:0',
    'inference_profile_arn_haiku' => env('AWS_BEDROCK_PROFILE_ARN_HAIKU', 'arn:aws:bedrock:us-east-2:103003181311:application-inference-profile/45qa3jiq9cnq'),
    'max_tokens_haiku' => env('AWS_BEDROCK_MAX_TOKENS_HAIKU', 500),
    'temperature_haiku' => env('AWS_BEDROCK_TEMPERATURE_HAIKU', 0.7),
    'top_p_haiku' => env('AWS_BEDROCK_TOP_P_HAIKU', 0.9),
    'top_k_haiku' => env('AWS_BEDROCK_TOP_K_HAIKU', 250),
    'system_prompt_haiku' => env('AWS_BEDROCK_SYSTEM_PROMPT_HAIKU', 'Tu nombre es Jarvis y eres un asistente financiero experto con conocimientos de contabilidad, finanzas y análisis de negocio. Ayudas a los usuarios a entender y gestionar sus finanzas empresariales. Tu información está actualizada hasta Octubre 2024 y es de el salvador principalmente la informacion. Cuando no sabes algo, lo indicas claramente. Respondes en español usando el mismo tono del usuario.'),
    
    // Claude 3 Sonnet - Configuración (ejemplo para añadir otro modelo)
    

];