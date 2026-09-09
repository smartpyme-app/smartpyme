<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proxy HTTP hacia Lucas (servicio de IA en Python/FastAPI).
 *
 * Centraliza todas las llamadas al servicio de Lucas para que el frontend
 * (web) y WhatsApp pasen SIEMPRE por el backend en lugar de hablar directo
 * con Lucas. Así el saneamiento de formato (ChatFormatter), el registro de
 * uso (CostoIA) y la identidad del usuario quedan bajo un único contrato.
 *
 * Endpoints de Lucas soportados:
 *   - POST /chat
 *   - GET  /conversations
 *   - POST /conversations/new
 *   - GET  /conversations/{id}/messages
 */
class LucasService
{
    /**
     * Base URL del servicio Lucas.
     */
    public function baseUrl(): string
    {
        return rtrim(config('lucas.base_url', 'http://localhost:8000'), '/');
    }

    /**
     * Headers comunes: X-API-Key si está configurada.
     */
    private function request()
    {
        $request = Http::timeout((int) config('lucas.timeout', 120));

        $apiKey = config('lucas.api_key');
        if (!empty($apiKey)) {
            $request = $request->withHeaders(['X-API-Key' => $apiKey]);
        }

        return $request;
    }

    /**
     * Error normalizado cuando Lucas falla. Registra y lanza excepción.
     */
    private function fail(\Illuminate\Http\Client\Response $response, string $context): void
    {
        Log::error('Error en la API de Lucas:', [
            'status' => $response->status(),
            'body' => $response->body(),
            'context' => $context,
        ]);

        throw new \RuntimeException('El servicio de IA no pudo procesar la solicitud (Lucas).');
    }

    /**
     * POST /chat
     */
    public function chat(array $payload): array
    {
        $response = $this->request()->post($this->baseUrl() . '/chat', $payload);

        if ($response->failed()) {
            $this->fail($response, 'chat');
        }

        return $response->json() ?? [];
    }

    /**
     * GET /conversations
     */
    public function conversations(array $params): array
    {
        $response = $this->request()->get($this->baseUrl() . '/conversations', $params);

        if ($response->failed()) {
            $this->fail($response, 'conversations');
        }

        return $response->json() ?? [];
    }

    /**
     * POST /conversations/new
     */
    public function newConversation(array $params): array
    {
        $response = $this->request()->post($this->baseUrl() . '/conversations/new', null, $params);

        if ($response->failed()) {
            $this->fail($response, 'conversations/new');
        }

        return $response->json() ?? [];
    }

    /**
     * GET /conversations/{id}/messages
     */
    public function conversationMessages(string $id, array $params): array
    {
        $response = $this->request()->get($this->baseUrl() . "/conversations/{$id}/messages", $params);

        if ($response->failed()) {
            $this->fail($response, "conversations/{$id}/messages");
        }

        return $response->json() ?? [];
    }
}
