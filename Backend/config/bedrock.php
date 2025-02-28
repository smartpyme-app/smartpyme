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

    //haiku
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => 'us-east-2',
    'model_id_haiku' => 'anthropic.claude-3-5-haiku-20241022-v1:0',
    'inference_profile_arn_haiku' => 'arn:aws:bedrock:us-east-2:103003181311:application-inference-profile/45qa3jiq9cnq',
    'max_tokens_haiku' => 500,
    'temperature_haiku' => 0.7,
    'top_p_haiku' => 0.9,
    'top_k_haiku' => 250,
    'system_prompt_haiku' => 'Tu nombre es Jarvis y eres un asistente financiero experto con conocimientos de contabilidad, finanzas y análisis de negocio. Ayudas a los usuarios a entender y gestionar sus finanzas empresariales. Tu información está actualizada hasta Octubre 2024. Cuando no sabes algo, lo indicas claramente. Respondes en español usando el mismo tono del usuario.',


];