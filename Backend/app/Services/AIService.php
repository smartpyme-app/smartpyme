<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Exception\AwsException;

class AIService
{
    protected $client;
    protected $modelId;
    protected $inferenceProfileArn;
    protected $maxTokens;
    protected $temperature;
    protected $topP;
    protected $topK;
    protected $systemPrompt;

    public function __construct(string $modelType = 'haiku')
    {
        // Inicializar el cliente de Bedrock
        $this->client = new BedrockRuntimeClient([
            'version' => 'latest',
            'region' => config('bedrock.region'),
            'credentials' => [
                'key'    => config('bedrock.key'),
                'secret' => config('bedrock.secret'),
            ],
        ]);

        $this->loadModelConfig($modelType);
    }

    public function useModel(string $modelType): self
    {
        $this->loadModelConfig($modelType);
        return $this;
    }


    protected function loadModelConfig(string $modelType): void
    {
        $this->modelId = config("bedrock.model_id_$modelType");
        $this->inferenceProfileArn = config("bedrock.inference_profile_arn_$modelType");
        $this->maxTokens = config("bedrock.max_tokens_$modelType");
        $this->temperature = config("bedrock.temperature_$modelType");
        $this->topP = config("bedrock.top_p_$modelType");
        $this->topK = config("bedrock.top_k_$modelType");
        $this->systemPrompt = config("bedrock.system_prompt_$modelType");
    }

    public function generateResponse(string $prompt, array $history = [], array $options = []): string
    {
        try {
            // Formatear los mensajes para Claude
            $formattedMessages = $this->formatMessages($history, $prompt);

            $systemPrompt = $options['systemPrompt'] ?? $this->systemPrompt;
            
            // Configurar los parámetros de generación con valores predeterminados o personalizados
            $maxTokens = $options['maxTokens'] ?? $this->maxTokens;
            $temperature = $options['temperature'] ?? $this->temperature;
            $topP = $options['topP'] ?? $this->topP;
            $topK = $options['topK'] ?? $this->topK;
            $systemPrompt = $options['systemPrompt'] ?? $this->systemPrompt;
            
            // Crear cuerpo de la solicitud para Claude
            $requestBody = [
                'anthropic_version' => 'bedrock-2023-05-31',
                'max_tokens' => (int)$maxTokens,
                'messages' => $formattedMessages,
                'temperature' => (float)$temperature,
                'top_p' => (float)$topP,
                'top_k' => (int)$topK,
                'system' => $systemPrompt
            ];
            
            // Log para depuración
            Log::debug('Solicitud a Bedrock:', [
                'modelId' => $this->modelId,
                'inferenceProfileArn' => $this->inferenceProfileArn,
                'body' => $requestBody
            ]);
    
            // Invocar al modelo
            $response = $this->client->invokeModel([
                'body' => json_encode($requestBody),
                'contentType' => 'application/json',
                'accept' => 'application/json',
                'modelId' => $this->inferenceProfileArn,
            ]);
    
            // Procesar la respuesta
            $result = json_decode($response->get('body')->getContents(), true);
            
            // Extraer el texto de la respuesta
            $botResponse = $this->extractResponseText($result);
            
            // Manejar respuesta vacía
            if (empty($botResponse)) {
                throw new \Exception('No se pudo obtener una respuesta clara del modelo');
            }
            
            return $botResponse;
            
        } catch (AwsException $e) {
            // Registrar errores específicos de AWS
            Log::error('Error en AWS Bedrock:', [
                'message' => $e->getMessage(),
                'awsErrorType' => $e->getAwsErrorType(),
                'awsErrorCode' => $e->getAwsErrorCode(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    protected function formatMessages(array $history, string $prompt): array
    {
        $formattedMessages = [];
        
        // Si hay historial, procesarlo
        if (!empty($history)) {
            foreach ($history as $message) {
                $formattedMessages[] = [
                    'role' => $message['role'],
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $message['content']
                        ]
                    ]
                ];
            }
        }
        
        // Añadir el mensaje actual del usuario
        $formattedMessages[] = [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'text',
                    'text' => $prompt
                ]
            ]
        ];
        
        return $formattedMessages;
    }

    protected function extractResponseText(array $result): string
    {
        $botResponse = '';
        
        if (isset($result['content']) && is_array($result['content'])) {
            foreach ($result['content'] as $content) {
                if ($content['type'] === 'text') {
                    $botResponse .= $content['text'];
                }
            }
        }
        
        if (empty($botResponse) && isset($result['error'])) {
            throw new \Exception('Error en la respuesta del modelo: ' . $result['error']);
        }
        
        // Si hay un error al extraer el texto, mostrar toda la respuesta para debug
        if (empty($botResponse)) {
            Log::warning('No se pudo extraer texto de la respuesta:', ['response' => $result]);
            throw new \Exception('No se pudo obtener una respuesta clara');
        }
        
        return $botResponse;
    }

    public function setMaxTokens(int $maxTokens): self
    {
        $this->maxTokens = $maxTokens;
        return $this;
    }

    public function setTemperature(float $temperature): self
    {
        $this->temperature = $temperature;
        return $this;
    }

    public function setTopP(float $topP): self
    {
        $this->topP = $topP;
        return $this;
    }

    public function setTopK(int $topK): self
    {
        $this->topK = $topK;
        return $this;
    }

    public function setSystemPrompt(string $systemPrompt): self
    {
        $this->systemPrompt = $systemPrompt;
        return $this;
    }
}