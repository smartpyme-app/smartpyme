<?php

namespace App\Listeners;

use App\Events\DteValidated;
use Illuminate\Support\Facades\Log;

class NotifyAccountingModule
{
    public function handle(DteValidated $event): void
    {
        $document = $event->document;

        Log::info('NotifyAccountingModule: DTE validated for accounting', [
            'dte_uuid' => $document->dte_uuid,
            'id_empresa' => $document->id_empresa,
            'total_amount' => $document->total_amount,
        ]);

        // Placeholder: future integration with Libro IVA or other accounting modules
    }
}
