<?php

namespace App\Services\MailIngest;

use App\Jobs\ProcessDteJob;
use App\Support\Dte\DteEmailAttachmentHelper;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class IngestMailService
{
    public function __construct(
        private readonly IngestRecipientResolver $resolver,
        private readonly InboundMimeParser $parser,
        private readonly EmailInboxService $inboxes,
    ) {
    }

    public function processIncoming(?string $spoolRoot = null, int $limit = 50): int
    {
        $root = $spoolRoot ?: storage_path('app/mail-ingest');
        $incoming = $root . '/incoming';
        if (!is_dir($incoming)) {
            return 0;
        }

        $files = glob($incoming . '/*.eml') ?: [];
        $processed = 0;
        foreach ($files as $emlPath) {
            if ($processed >= $limit) {
                break;
            }
            $this->processEmlFile($root, $emlPath);
            $processed++;
        }

        return $processed;
    }

    public function processEmlFile(string $root, string $emlPath): string
    {
        $base = basename($emlPath, '.eml');
        $metaPath = dirname($emlPath) . '/' . $base . '.meta.json';
        $processingDir = $root . '/processing';
        $failedDir = $root . '/failed';
        File::ensureDirectoryExists($processingDir);
        File::ensureDirectoryExists($failedDir);

        $emlProcessing = $processingDir . '/' . $base . '.eml';
        $metaProcessing = $processingDir . '/' . $base . '.meta.json';

        if (!@rename($emlPath, $emlProcessing)) {
            return 'busy';
        }
        if (is_file($metaPath)) {
            @rename($metaPath, $metaProcessing);
        }

        $raw = file_get_contents($emlProcessing);
        if ($raw === false) {
            $this->fail($emlProcessing, $metaProcessing, $failedDir, 'unreadable', []);

            return 'error';
        }

        $meta = [];
        if (is_file($metaProcessing)) {
            $decoded = json_decode((string) file_get_contents($metaProcessing), true);
            $meta = is_array($decoded) ? $decoded : [];
        }

        $candidates = $meta['recipient_candidates'] ?? [];
        $token = $this->resolver->resolveToken(is_array($candidates) ? $candidates : []);
        if ($token === null) {
            $this->fail($emlProcessing, $metaProcessing, $failedDir, 'unknown_token', $meta);

            return 'unknown_token';
        }

        $inbox = $this->inboxes->findActiveByToken($token);
        if (!$inbox) {
            $this->fail($emlProcessing, $metaProcessing, $failedDir, 'unknown_token', $meta);

            return 'unknown_token';
        }

        $empresa = $inbox->empresa;
        if ($empresa && isset($empresa->activo) && !$empresa->activo) {
            $inbox->increment('emails_rejected');
            $this->fail($emlProcessing, $metaProcessing, $failedDir, 'empresa_inactive', $meta);

            return 'empresa_inactive';
        }

        $account = $inbox->userEmailAccount()->withoutGlobalScopes()->first();
        if (!$account || !$account->is_active) {
            $this->fail($emlProcessing, $metaProcessing, $failedDir, 'inbox_inactive', $meta);

            return 'inbox_inactive';
        }

        $attachments = $this->parser->attachments($raw);
        $messageId = $this->parser->messageId($raw) ?: ('ingest-' . $base);
        $groups = DteEmailAttachmentHelper::groupAttachments(
            'ingest-' . $inbox->id . '-' . sha1($messageId),
            $attachments
        );

        $inbox->increment('emails_received');
        $inbox->update(['last_email_at' => now()]);

        if ($groups === []) {
            $this->forget($emlProcessing, $metaProcessing);
            Log::info('dte-ingest: no dte attachments', [
                'inbox_id' => $inbox->id,
                'id_empresa' => $inbox->id_empresa,
                'message_id' => $messageId,
            ]);

            return 'no_dte';
        }

        $imported = 0;
        foreach ($groups as $emailData) {
            if ($this->dispatchDte($account, $emailData)) {
                $imported++;
            }
        }

        if ($imported > 0) {
            $inbox->increment('dtes_imported', $imported);
            $inbox->update(['last_dte_at' => now()]);
        }

        $this->forget($emlProcessing, $metaProcessing);

        return 'processed';
    }

    /**
     * @param  array<string, mixed>  $emailData
     */
    private function dispatchDte($account, array $emailData): bool
    {
        $tempDir = storage_path('app/temp/dtes');
        File::ensureDirectoryExists($tempDir);
        $prefix = ($emailData['email_message_id'] ?? uniqid()) . '-' . uniqid();
        $format = $emailData['source_format'] ?? DteEmailAttachmentHelper::FORMAT_JSON;
        $ext = $format === DteEmailAttachmentHelper::FORMAT_XML ? 'xml' : 'json';

        $sourceTempPath = $tempDir . '/' . $prefix . '.' . $ext;
        File::put($sourceTempPath, $emailData['source_content']);

        $pdfTempPath = null;
        if (!empty($emailData['pdf_content'])) {
            $pdfTempPath = $tempDir . '/' . $prefix . '.pdf';
            File::put($pdfTempPath, $emailData['pdf_content']);
        }

        $acuseTempPath = null;
        if (!empty($emailData['acuse_content'])) {
            $acuseTempPath = $tempDir . '/' . $prefix . '-acuse.xml';
            File::put($acuseTempPath, $emailData['acuse_content']);
        }

        try {
            $result = ProcessDteJob::dispatchSync(
                $account,
                $sourceTempPath,
                $emailData['email_message_id'],
                $format,
                $pdfTempPath,
                $acuseTempPath
            );

            return $result !== 'failed';
        } catch (\Throwable $e) {
            Log::warning('dte-ingest: ProcessDteJob failed', [
                'id_empresa' => $account->id_empresa,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function fail(string $eml, string $metaFile, string $failedDir, string $reason, array $meta): void
    {
        $meta['reason'] = $reason;
        $base = basename($eml, '.eml');
        if (is_file($metaFile) || $meta !== []) {
            file_put_contents(
                $failedDir . '/' . $base . '.meta.json',
                json_encode($meta, JSON_UNESCAPED_SLASHES) . "\n"
            );
        }
        if (is_file($eml)) {
            @rename($eml, $failedDir . '/' . $base . '.eml');
        }
        @unlink($metaFile);
        Log::info('dte-ingest: rejected', ['reason' => $reason, 'file' => $base]);
    }

    private function forget(string $eml, string $metaFile): void
    {
        if (is_file($eml)) {
            @unlink($eml);
        }
        if (is_file($metaFile)) {
            @unlink($metaFile);
        }
    }
}
