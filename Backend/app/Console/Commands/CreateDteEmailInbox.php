<?php

namespace App\Console\Commands;

use App\Models\Admin\Empresa;
use App\Models\User;
use App\Services\MailIngest\EmailInboxService;
use Illuminate\Console\Command;

class CreateDteEmailInbox extends Command
{
    protected $signature = 'dte:inbox-create {id_empresa} {--user_id= : Usuario dueño de la cuenta sintética} {--token= : Token fijo (pruebas); si se omite es aleatorio}';

    protected $description = 'Activa una dirección token@ingest.smartpyme.site para una empresa';

    public function handle(EmailInboxService $inboxes): int
    {
        $idEmpresa = (int) $this->argument('id_empresa');
        $empresa = Empresa::query()->find($idEmpresa);
        if (!$empresa) {
            $this->error('Empresa no encontrada');

            return 1;
        }

        $userId = $this->option('user_id');
        if ($userId) {
            $user = User::withoutGlobalScopes()->where('id', (int) $userId)->where('id_empresa', $idEmpresa)->first();
        } else {
            $user = User::withoutGlobalScopes()->where('id_empresa', $idEmpresa)->orderBy('id')->first();
        }

        if (!$user) {
            $this->error('No hay usuario de esa empresa. Pasa --user_id=');

            return 1;
        }

        $inbox = $inboxes->createForEmpresa(
            $idEmpresa,
            (int) $user->id,
            $this->option('token') ? (string) $this->option('token') : null
        );
        $this->info('Dirección: ' . $inbox->email);
        $this->line('token: ' . $inbox->token);
        $this->line('status: ' . $inbox->status);
        $this->line('id_empresa: ' . $inbox->id_empresa);

        return 0;
    }
}
