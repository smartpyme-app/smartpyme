<?php

namespace App\Services;

use App\Models\CostoIA;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class AIService
{
    protected $source = 'Web';
    protected $systemPrompt;
    protected $lastResponse = [];

    public function __construct(string $modelType = 'haiku')
    {
        // El modelo lo gestiona el servicio de IA (Lucas) internamente
        // (LUCAS_MODEL_PRESET). Se conserva el parámetro por compatibilidad
        // con los ServiceProviders existentes.
    }

    public function useModel(string $modelType): self
    {
        // Lucas selecciona el modelo por su cuenta; no-op por compatibilidad.
        return $this;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;
        return $this;
    }

    public function getLastResponse(): array
    {
        return $this->lastResponse;
    }

    public function getSuggestions(): array
    {
        return $this->lastResponse['suggestions'] ?? [];
    }

    public function estimateTokenCount(string $text): int
    {
        // Estimación aproximada: Claude usa ~4 caracteres por token en promedio
        return (int) ceil(mb_strlen($text) / 4);
    }

    public function logTokenUsage(string $prompt, array $history, string $response): array
    {
        $promptTokens = $this->estimateTokenCount($prompt);
        $historyTokens = 0;

        foreach ($history as $message) {
            $historyTokens += $this->estimateTokenCount($message['content']);
        }

        $responseTokens = $this->estimateTokenCount($response);
        $totalTokens = $promptTokens + $historyTokens + $responseTokens;

        $usage = [
            'prompt_tokens' => $promptTokens,
            'history_tokens' => $historyTokens,
            'response_tokens' => $responseTokens,
            'total_tokens' => $totalTokens
        ];

        // Log::info('Token usage', $usage);

        return $usage;
    }

    protected function estimateCost(int $inputTokens, int $outputTokens, string $modelId): float
    {
        // Tarifas por millón de tokens (ajustar según tarifas de AWS Bedrock)
        $rates = [
            'haiku' => [
                'input' => 0.25, // $0.25 por millón de tokens de entrada
                'output' => 1.25  // $1.25 por millón de tokens de salida
            ],
            'sonnet' => [
                'input' => 3.00,
                'output' => 15.00
            ],
            'opus' => [
                'input' => 15.00,
                'output' => 75.00
            ]
        ];

        // Determinar qué modelo se está usando (haiku como predeterminado)
        $modelType = 'haiku';
        if (strpos($modelId, 'sonnet') !== false) {
            $modelType = 'sonnet';
        } elseif (strpos($modelId, 'opus') !== false) {
            $modelType = 'opus';
        }

        // Calcular costo ($ por millón de tokens)
        $inputCost = ($inputTokens / 1000000) * $rates[$modelType]['input'];
        $outputCost = ($outputTokens / 1000000) * $rates[$modelType]['output'];

        return $inputCost + $outputCost;
    }

    protected function registerUsage(string $prompt, string $response, array $options = []): void
    {
        try {
            // Estimar tokens
            $inputTokens = $this->estimateTokenCount($prompt);
            // También incluir tokens del historial y system prompt si están disponibles
            if (isset($options['history'])) {
                foreach ($options['history'] as $message) {
                    $inputTokens += $this->estimateTokenCount($message['content'] ?? '');
                }
            }
            if (isset($this->systemPrompt)) {
                $inputTokens += $this->estimateTokenCount($this->systemPrompt);
            }

            $outputTokens = $this->estimateTokenCount($response);

            // El modelo usado lo reporta Lucas; fallback al modelo haiku de Bedrock
            $modelId = $this->lastResponse['modelUsed'] ?? config('bedrock.model_id_haiku');

            // Estimar costo
            $cost = $this->estimateCost($inputTokens, $outputTokens, $modelId);

            // Obtener ID de usuario y empresa
            $userId = Auth::id() ?? null;
            $empresaId = session('id_empresa') ?? 1; // Ajustar según cómo manejas la empresa

            // Registrar en la base de datos
            CostoIA::create([
                'id_usuario' => $userId,
                'id_empresa' => $empresaId,
                'modelo' => $modelId,
                'tokens_entrada' => $inputTokens,
                'tokens_salida' => $outputTokens,
                'costo_estimado' => $cost,
                'consulta' => substr($prompt, 0, 1000), // Limitar tamaño si es necesario
                'respuesta' => substr($response, 0, 1000) // Limitar tamaño si es necesario
            ]);
        } catch (\Exception $e) {
            // Loguear error pero no interrumpir el flujo
            Log::error('Error al registrar uso de IA:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function generateResponse(string $prompt, array $history = [], array $options = []): string
    {
        $this->lastResponse = [];

        // Resolver la identidad requerida por Lucas
        $user = Auth::user();
        $payload = [
            'message' => $prompt,
            'user_id' => $options['user_id'] ?? Auth::id(),
            'empresa_id' => $options['empresa_id'] ?? session('id_empresa'),
            'user_type' => $options['user_type'] ?? ($user ? $user->tipo : 'Usuario'),
            'source' => $options['source'] ?? $this->source,
        ];

        // El contexto de la conversación lo mantiene Lucas (conversation_id)
        if (!empty($options['conversation_id'])) {
            $payload['conversation_id'] = $options['conversation_id'];
        }

        $request = Http::timeout((int) config('lucas.timeout', 120));

        $apiKey = config('lucas.api_key');
        if (!empty($apiKey)) {
            $request = $request->withHeaders(['X-API-Key' => $apiKey]);
        }

        $response = $request->post(rtrim(config('lucas.base_url'), '/') . '/chat', $payload);

        if ($response->failed()) {
            Log::error('Error en la API de Lucas:', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payload' => $payload,
            ]);
            throw new \Exception('El servicio de IA no pudo procesar la solicitud (Lucas).');
        }

        $data = $response->json() ?? [];

        $this->lastResponse = $data;

        $botResponse = $data['message'] ?? '';

        if (empty($botResponse)) {
            throw new \Exception('No se pudo obtener una respuesta clara del servicio de IA.');
        }

        $this->registerUsage($prompt, $botResponse, [
            'history' => $history,
        ]);

        return $botResponse;
    }

    public function setMaxTokens(int $maxTokens): self
    {
        // Lucas gestiona los parámetros de generación; no-op por compatibilidad.
        return $this;
    }

    public function setTemperature(float $temperature): self
    {
        // Lucas gestiona los parámetros de generación; no-op por compatibilidad.
        return $this;
    }

    public function setTopP(float $topP): self
    {
        // Lucas gestiona los parámetros de generación; no-op por compatibilidad.
        return $this;
    }

    public function setTopK(int $topK): self
    {
        // Lucas gestiona los parámetros de generación; no-op por compatibilidad.
        return $this;
    }

    public function setSystemPrompt(string $systemPrompt): self
    {
        // Lucas usa su propio system prompt interno; se conserva solo para
        // mantener el registro de uso (registerUsage).
        $this->systemPrompt = $systemPrompt;
        return $this;
    }
}
