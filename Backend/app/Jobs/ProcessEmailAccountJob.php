<?php

namespace App\Jobs;

use App\Models\DteManagement\SyncLog;
use App\Models\DteManagement\UserEmailAccount;
use App\Services\Gmail\GmailReaderService;
use App\Services\Imap\ImapReaderService;
use App\Support\Dte\DteEmailAttachmentHelper;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ProcessEmailAccountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 120, 300];

    public function __construct(
        protected UserEmailAccount $account,
        protected Carbon $dateFrom,
        protected Carbon $dateTo
    ) {
        $this->onQueue('default');
    }

    public function handle(GmailReaderService $gmailReader, ImapReaderService $imapReader): void
    {
        $account = $this->account->fresh();
        if (!$account || !$account->is_active) {
            return;
        }

        $syncLog = SyncLog::withoutGlobalScopes()->create([
            'id_empresa' => $account->id_empresa,
            'user_email_account_id' => $account->id,
            'started_at' => now(),
            'status' => 'running',
            'emails_scanned' => 0,
            'dtes_found' => 0,
            'dtes_processed' => 0,
            'dtes_failed' => 0,
        ]);

        $failureDetails = [];

        try {
            $reader = $account->provider === 'gmail' ? $gmailReader : $imapReader;
            $emailsWithDtes = $reader->getEmailsWithDteAttachments($account, $this->dateFrom, $this->dateTo);

            $syncLog->update([
                'emails_scanned' => $emailsWithDtes->count(),
                'dtes_found' => $emailsWithDtes->count(),
            ]);

            foreach ($emailsWithDtes as $emailData) {
                $sourceTempPath = null;
                $pdfTempPath = null;
                $acuseTempPath = null;

                try {
                    $tempDir = storage_path('app/temp/dtes');
                    File::ensureDirectoryExists($tempDir);
                    $prefix = ($emailData['email_message_id'] ?? uniqid()).'-'.uniqid();
                    $format = $emailData['source_format'] ?? DteEmailAttachmentHelper::FORMAT_JSON;
                    $ext = $format === DteEmailAttachmentHelper::FORMAT_XML ? 'xml' : 'json';

                    $sourceTempPath = $tempDir.'/'.$prefix.'.'.$ext;
                    File::put($sourceTempPath, $emailData['source_content']);

                    if (!empty($emailData['pdf_content'])) {
                        $pdfTempPath = $tempDir.'/'.$prefix.'.pdf';
                        File::put($pdfTempPath, $emailData['pdf_content']);
                    }

                    if (!empty($emailData['acuse_content'])) {
                        $acuseTempPath = $tempDir.'/'.$prefix.'-acuse.xml';
                        File::put($acuseTempPath, $emailData['acuse_content']);
                    }

                    $result = ProcessDteJob::dispatchSync(
                        $account,
                        $sourceTempPath,
                        $emailData['email_message_id'],
                        $format,
                        $pdfTempPath,
                        $acuseTempPath
                    );

                    if ($result === 'duplicate') {
                        $syncLog->increment('dtes_duplicates');
                    } else {
                        $syncLog->increment('dtes_processed');
                    }
                } catch (\Throwable $e) {
                    $this->cleanupTemp($sourceTempPath, $pdfTempPath, $acuseTempPath);
                    Log::warning('ProcessEmailAccountJob: Failed to process DTE', [
                        'error' => $e->getMessage(),
                    ]);
                    $failureDetails[] = [
                        'email_message_id' => $emailData['email_message_id'] ?? null,
                        'error' => $e->getMessage(),
                    ];
                    $syncLog->increment('dtes_failed');
                }
            }

            $account->update(['last_sync_at' => now()]);

            $syncLog->update([
                'finished_at' => now(),
                'status' => 'completed',
                'failure_details' => $failureDetails ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::error('ProcessEmailAccountJob failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
            $syncLog->update([
                'finished_at' => now(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function cleanupTemp(?string $sourceTempPath, ?string $pdfTempPath, ?string $acuseTempPath): void
    {
        if ($sourceTempPath && File::exists($sourceTempPath)) {
            File::delete($sourceTempPath);
        }
        if ($pdfTempPath && File::exists($pdfTempPath)) {
            File::delete($pdfTempPath);
        }
        if ($acuseTempPath && File::exists($acuseTempPath)) {
            File::delete($acuseTempPath);
        }
    }
}
