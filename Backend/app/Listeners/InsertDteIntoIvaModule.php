<?php

namespace App\Listeners;

use App\Events\DteValidated;
use App\Services\Dte\DteToIvaService;
use Illuminate\Support\Facades\Log;

class InsertDteIntoIvaModule
{
    public function __construct(
        protected DteToIvaService $dteToIvaService
    ) {
    }

    public function handle(DteValidated $event): void
    {
        $document = $event->document;

        if ($document->validation_status !== 'valid') {
            return;
        }

        try {
            $result = $this->dteToIvaService->insertFromDteDocument($document);

            if (!empty($result['skipped'])) {
                Log::info('InsertDteIntoIvaModule: DTE skipped', [
                    'dte_uuid' => $document->dte_uuid,
                    'reason' => $result['skipped'],
                ]);
                return;
            }

            if ($result['success']) {
                Log::info('InsertDteIntoIvaModule: DTE inserted', [
                    'dte_uuid' => $document->dte_uuid,
                    'compra_id' => $result['compra_id'] ?? null,
                    'gasto_id' => $result['gasto_id'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('InsertDteIntoIvaModule: Failed to insert DTE', [
                'dte_uuid' => $document->dte_uuid,
                'error' => $e->getMessage(),
            ]);

            $document->update([
                'processing_status' => 'failed',
                'processing_errors' => $e->getMessage(),
            ]);
        }
    }
}
