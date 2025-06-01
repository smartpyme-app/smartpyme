<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsApp\WhatsAppSession;
use App\Models\WhatsApp\WhatsAppMessage;
use App\Models\Admin\Empresa;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Exception;

class WhatsAppService
{
    protected $messageHandler;
    protected $responseBuilder;

    public function __construct(MessageHandler $messageHandler, ResponseBuilder $responseBuilder)
    {
        $this->messageHandler = $messageHandler;
        $this->responseBuilder = $responseBuilder;
    }

    public function processIncomingMessage(array $webhookData): array
    {
        try {
            $messageData = $this->extractMessageData($webhookData);
            
            if (!$messageData) {
                return ['success' => false, 'error' => 'No message data found'];
            }

            $session = WhatsAppSession::findOrCreateByNumber($messageData['from']);

            $this->saveIncomingMessage($messageData, $session);

            $response = $this->messageHandler->handle($session, $messageData['body']);

            if ($response) {
                $sent = $this->responseBuilder->sendMessage($messageData['from'], $response);
                
                if ($sent) {
                    $this->saveOutgoingMessage($messageData['from'], $response, $session);
                }

                return ['success' => $sent, 'response' => $response];
            }

            return ['success' => true, 'response' => 'No response needed'];

        } catch (Exception $e) {
            Log::error('Error procesando mensaje WhatsApp', [
                'error' => $e->getMessage(),
                'data' => $webhookData
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function extractMessageData(array $webhookData): ?array
    {
        try {
            $entry = $webhookData['entry'][0];
            $changes = $entry['changes'][0];
            $value = $changes['value'];

            if (!isset($value['messages']) || empty($value['messages'])) {
                return null;
            }

            $message = $value['messages'][0];

            if (!isset($message['text']['body'])) {
                return null;
            }

            return [
                'from' => $message['from'],
                'body' => $message['text']['body'],
                'id' => $message['id'],
                'timestamp' => $message['timestamp'],
                'metadata' => $value['metadata'] ?? []
            ];

        } catch (Exception $e) {
            Log::error('Error extrayendo datos del mensaje', [
                'error' => $e->getMessage(),
                'data' => $webhookData
            ]);
            return null;
        }
    }

    private function saveIncomingMessage(array $messageData, WhatsAppSession $session): void
    {
        WhatsAppMessage::createIncoming(
            $messageData['from'],
            $messageData['body'],
            $session->empresa,
            $session->usuario
        );
    }

    private function saveOutgoingMessage(string $number, string $content, WhatsAppSession $session): void
    {
        WhatsAppMessage::createOutgoing(
            $number,
            $content,
            $session->empresa,
            $session->usuario
        );
    }
}