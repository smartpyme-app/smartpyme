<?php

namespace App\Console\Commands;

use App\Services\MailIngest\IngestSpoolPurge;
use Illuminate\Console\Command;

class IngestDteSpoolPurgeCommand extends Command
{
    protected $signature = 'dte:ingest-purge {--days=7 : Borrar archivos del spool más viejos que N días}';

    protected $description = 'Elimina .eml/.meta antiguos del spool de ingest';

    public function handle(IngestSpoolPurge $purge): int
    {
        $days = max(1, (int) $this->option('days'));
        $deleted = $purge->purge(storage_path('app/mail-ingest'), $days);
        $this->info("Eliminados: {$deleted}");

        return 0;
    }
}
