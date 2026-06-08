<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEmailAccountJob;
use App\Models\DteManagement\UserEmailAccount;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncDteEmailAccounts extends Command
{
    protected $signature = 'dte:sync-accounts {--dias=30 : Días hacia atrás para buscar correos}';

    protected $description = 'Sincroniza cuentas de correo activas del módulo DTE (descarga DTEs)';

    public function handle(): int
    {
        $daysBack = (int) $this->option('dias');
        $dateFrom = Carbon::now()->subDays($daysBack);
        $dateTo = Carbon::now();

        $accounts = UserEmailAccount::withoutGlobalScopes()
            ->where('is_active', true)
            ->get();

        if ($accounts->isEmpty()) {
            $this->info('No hay cuentas activas para sincronizar.');
            return 0;
        }

        set_time_limit(0);

        foreach ($accounts as $account) {
            $this->line("Sincronizando: {$account->email} (id: {$account->id})...");
            ProcessEmailAccountJob::dispatchSync($account, $dateFrom, $dateTo);
            $account->refresh();
            $this->line("  Completado. Última sync: {$account->last_sync_at}");
        }

        $this->info("Sincronización finalizada para {$accounts->count()} cuenta(s).");

        return 0;
    }
}
