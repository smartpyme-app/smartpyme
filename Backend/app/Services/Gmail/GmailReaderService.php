<?php

namespace App\Services\Gmail;

use App\Contracts\EmailReaderInterface;
use App\Models\DteManagement\UserEmailAccount;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class GmailReaderService implements EmailReaderInterface
{
    public function __construct(
        protected GmailOAuthService $gmailOAuthService
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function getEmailsWithDteAttachments(UserEmailAccount $account, Carbon $from, Carbon $to): Collection
    {
        $this->gmailOAuthService->refreshAccessToken($account);
        $account->refresh();

        $client = $this->createAuthenticatedClient($account);
        $service = new Gmail($client);

        $results = collect();
        $pageToken = null;

        do {
            $listParams = [
                'q' => "after:{$from->format('Y/m/d')} before:{$to->format('Y/m/d')} has:attachment",
                'maxResults' => 50,
            ];
            if ($pageToken) {
                $listParams['pageToken'] = $pageToken;
            }

            $response = $service->users_messages->listUsersMessages('me', $listParams);
            $messages = $response->getMessages() ?? [];

            foreach ($messages as $messageRef) {
                try {
                    $message = $service->users_messages->get('me', $messageRef->getId(), ['format' => 'full']);
                    $items = $this->extractDteAttachments($service, $message);
                    foreach ($items as $item) {
                        $results->push($item);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Gmail: Error reading message', [
                        'message_id' => $messageRef->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken);

        return $results;
    }

    /**
     * Extract JSON and PDF attachments from a Gmail message.
     *
     * @return array<int, array{email_message_id: string, json_content: string, pdf_content: ?string}>
     */
    protected function extractDteAttachments(Gmail $service, object $message): array
    {
        $messageId = $message->getId();
        $payload = $message->getPayload();
        $parts = $payload->getParts() ?? [];

        $jsonContent = null;
        $pdfContent = null;

        foreach ($parts as $part) {
            $filename = $part->getFilename() ?? '';
            $body = $part->getBody();
            if (!$body) {
                if ($part->getParts()) {
                    foreach ($part->getParts() as $subPart) {
                        $subBody = $subPart->getBody();
                        if ($subBody && $subBody->getAttachmentId()) {
                            $ext = strtolower(pathinfo($subPart->getFilename() ?? '', PATHINFO_EXTENSION));
                            $data = $this->getAttachmentData($service, $messageId, $subBody->getAttachmentId());
                            if ($ext === 'json') {
                                $jsonContent = $this->ensureUtf8($data);
                            } elseif ($ext === 'pdf') {
                                $pdfContent = $data;
                            }
                        }
                    }
                }
                continue;
            }

            if ($body->getAttachmentId()) {
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $data = $this->getAttachmentData($service, $messageId, $body->getAttachmentId());
                if ($ext === 'json') {
                    $jsonContent = $this->ensureUtf8($data);
                } elseif ($ext === 'pdf') {
                    $pdfContent = $data;
                }
            }
        }

        if ($jsonContent === null) {
            return [];
        }

        return [[
            'email_message_id' => $messageId,
            'json_content' => $jsonContent,
            'pdf_content' => $pdfContent,
        ]];
    }

    protected function getAttachmentData(Gmail $service, string $messageId, string $attachmentId): string
    {
        $attachment = $service->users_messages_attachments->get('me', $messageId, $attachmentId);
        return base64_decode(strtr($attachment->getData(), '-_', '+/'));
    }

    /**
     * Ensure string is valid UTF-8 to avoid "JSON encode payload Error code: 5" when queuing.
     */
    protected function ensureUtf8(string $data): string
    {
        if ($data === '') {
            return $data;
        }
        $encoding = mb_detect_encoding($data, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $data = mb_convert_encoding($data, 'UTF-8', $encoding);
        }
        return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
    }

    protected function createAuthenticatedClient(UserEmailAccount $account): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId(config('services.gmail.client_id'));
        $client->setClientSecret(config('services.gmail.client_secret'));
        $client->setAccessToken([
            'access_token' => $account->access_token,
            'refresh_token' => $account->refresh_token,
            'expires_in' => 0,
        ]);
        $client->setScopes([Gmail::GMAIL_READONLY]);
        return $client;
    }
}
