<?php

namespace App\Console\Commands;

use App\Services\MailIngest\IngestMailService;
use Illuminate\Console\Command;

class IngestDteSpoolCommand extends Command
{
    protected $signature = 'dte:ingest-spool {--limit=50 : Máximo de .eml por corrida}';

    protected $description = 'Procesa correos del spool de ingest hacia el pipeline DTE existente';

    public function handle(IngestMailService $ingest): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $count = $ingest->processIncoming(storage_path('app/mail-ingest'), $limit);
        $this->info("Procesados: {$count}");

        return 0;
    }
}
