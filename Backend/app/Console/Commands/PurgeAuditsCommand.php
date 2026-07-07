<?php

namespace App\Console\Commands;

use App\Models\Audit\Audit;
use Illuminate\Console\Command;

class PurgeAuditsCommand extends Command
{
    protected $signature = 'auditoria:purge {--months=}';

    protected $description = 'Elimina registros de auditoría más antiguos que el período de retención';

    public function handle(): int
    {
        $months = (int) ($this->option('months') ?: config('audit.purge_months', 6));
        $cutoff = now()->subMonths($months);

        $deleted = 0;
        do {
            $batch = Audit::withoutGlobalScopes()
                ->where('created_at', '<', $cutoff)
                ->limit(1000)
                ->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Eliminados {$deleted} registros anteriores a {$cutoff->toDateString()} (retención: {$months} meses).");

        return self::SUCCESS;
    }
}
