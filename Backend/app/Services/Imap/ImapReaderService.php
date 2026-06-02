<?php

namespace App\Services\Imap;

use App\Contracts\EmailReaderInterface;
use App\Models\DteManagement\UserEmailAccount;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\ClientManager;

class ImapReaderService implements EmailReaderInterface
{
    /**
     * {@inheritdoc}
     */
    public function getEmailsWithDteAttachments(UserEmailAccount $account, Carbon $from, Carbon $to): Collection
    {
        $clientConfig = $this->buildClientConfig($account);
        $client = (new ClientManager())->make($clientConfig);

        $client->connect();

        $results = collect();

        try {
            $folder = $client->getFolderByName('INBOX');
            if (!$folder) {
                return $results;
            }

            $messages = $folder->query()
                ->since($from)
                ->before($to->addDay())
                ->get();

            foreach ($messages as $message) {
                try {
                    $items = $this->extractDteAttachments($message, $account);
                    foreach ($items as $item) {
                        $results->push($item);
                    }
                } catch (\Throwable $e) {
                    Log::warning('IMAP: Error reading message', [
                        'uid' => $message->getUid(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } finally {
            $client->disconnect();
        }

        return $results;
    }

    /**
     * @return array<int, array{email_message_id: string, json_content: string, pdf_content: ?string}>
     */
    protected function extractDteAttachments($message, UserEmailAccount $account): array
    {
        $messageId = 'imap-' . $account->id . '-' . $message->getUid();
        $attachments = $message->getAttachments();

        $jsonContent = null;
        $pdfContent = null;

        foreach ($attachments as $attachment) {
            $filename = $attachment->getName() ?: $attachment->getFilename() ?: '';
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            try {
                $content = $attachment->getContent();
                if ($ext === 'json') {
                    $jsonContent = $this->ensureUtf8($content);
                } elseif ($ext === 'pdf') {
                    $pdfContent = $content;
                }
            } catch (\Throwable $e) {
                Log::warning('IMAP: Could not get attachment content', ['filename' => $filename]);
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

    protected function buildClientConfig(UserEmailAccount $account): array
    {
        $encryption = $account->imap_encryption ?? 'ssl';
        $encryptionMap = [
            'ssl' => 'ssl',
            'tls' => 'tls',
            'starttls' => 'starttls',
            'none' => 'notls',
        ];

        return [
            'host' => $account->imap_host,
            'port' => (int) ($account->imap_port ?? 993),
            'encryption' => $encryptionMap[strtolower($encryption)] ?? 'ssl',
            'validate_cert' => true,
            'username' => $account->imap_user,
            'password' => $account->imap_password,
            'protocol' => 'imap',
            'timeout' => 30,
        ];
    }
}
