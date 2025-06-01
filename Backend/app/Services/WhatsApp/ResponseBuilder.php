<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ResponseBuilder
{
    private $apiUrl;
    private $accessToken;

    public function __construct()
    {
        $this->apiUrl = config('services.whatsapp.api_url');
        $this->accessToken = config('services.whatsapp.access_token');
    }

    /**
     * Enviar mensaje de texto
     */
    public function sendMessage(string $to, string $message): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'body' => $message
                ]
            ]);

            if ($response->successful()) {
                Log::info('Mensaje WhatsApp enviado exitosamente', [
                    'to' => $to,
                    'message_length' => strlen($message)
                ]);
                return true;
            }

            Log::error('Error enviando mensaje WhatsApp', [
                'to' => $to,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return false;

        } catch (Exception $e) {
            Log::error('Excepción enviando mensaje WhatsApp', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Enviar mensaje con botones interactivos
     */
    public function sendButtonMessage(string $to, string $bodyText, array $buttons): bool
    {
        try {
            $buttonData = [];
            foreach ($buttons as $index => $button) {
                $buttonData[] = [
                    'type' => 'reply',
                    'reply' => [
                        'id' => $button['id'] ?? "btn_$index",
                        'title' => substr($button['title'], 0, 20) // Límite de WhatsApp
                    ]
                ];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => [
                        'text' => $bodyText
                    ],
                    'action' => [
                        'buttons' => $buttonData
                    ]
                ]
            ]);

            if ($response->successful()) {
                Log::info('Mensaje con botones enviado exitosamente', [
                    'to' => $to,
                    'buttons_count' => count($buttons)
                ]);
                return true;
            }

            Log::error('Error enviando mensaje con botones', [
                'to' => $to,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return false;

        } catch (Exception $e) {
            Log::error('Excepción enviando mensaje con botones', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Enviar lista interactiva
     */
    public function sendListMessage(string $to, string $bodyText, string $buttonText, array $sections): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'list',
                    'body' => [
                        'text' => $bodyText
                    ],
                    'action' => [
                        'button' => $buttonText,
                        'sections' => $sections
                    ]
                ]
            ]);

            if ($response->successful()) {
                Log::info('Lista interactiva enviada exitosamente', [
                    'to' => $to,
                    'sections_count' => count($sections)
                ]);
                return true;
            }

            Log::error('Error enviando lista interactiva', [
                'to' => $to,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return false;

        } catch (Exception $e) {
            Log::error('Excepción enviando lista interactiva', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Enviar documento/archivo
     */
    public function sendDocument(string $to, string $documentUrl, string $caption = '', string $filename = ''): bool
    {
        try {
            $document = ['link' => $documentUrl];
            
            if ($caption) {
                $document['caption'] = $caption;
            }
            
            if ($filename) {
                $document['filename'] = $filename;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'document',
                'document' => $document
            ]);

            if ($response->successful()) {
                Log::info('Documento enviado exitosamente', [
                    'to' => $to,
                    'document_url' => $documentUrl
                ]);
                return true;
            }

            Log::error('Error enviando documento', [
                'to' => $to,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return false;

        } catch (Exception $e) {
            Log::error('Excepción enviando documento', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }
}