<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use App\Models\Admin\Empresa;
use App\Models\Token\EmpresaCliente;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GenerateOAuthCredentialsForCompanies extends Command
{

    protected $signature = 'passport:generate-company-credentials {--user_id= : ID del usuario administrador para crear los clientes}';


    protected $description = 'Genera credenciales OAuth para todas las empresas registradas';

    /**
     * The client repository instance.
     *
     * @var \Laravel\Passport\ClientRepository
     */
    protected $clients;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(ClientRepository $clients)
    {
        parent::__construct();
        $this->clients = $clients;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $userId = $this->option('user_id');

        if (!$userId) {

            $user = User::where('tipo', 'Administrador')->first();

            if (!$user) {
                $this->error('No se encontró un usuario administrador en el sistema.');
                return 1;
            }

            $userId = $user->id;
        } else {
            $user = User::find($userId);

            if (!$user) {
                $this->error('El usuario especificado no existe.');
                return 1;
            }
        }

        $empresas = Empresa::get();

        if ($empresas->isEmpty()) {
            $this->info('No hay empresas activas en el sistema.');
            return 0;
        }

        $this->info('Generando credenciales para ' . $empresas->count() . ' empresas...');
        $bar = $this->output->createProgressBar($empresas->count());
        $bar->start();

        $createdCredentials = [];
        $skippedCompanies = [];

        foreach ($empresas as $empresa) {
            // Verificar si la empresa ya tiene un cliente
            $empresaCliente = EmpresaCliente::where('id_empresa', $empresa->id)
                               ->where('estado', 1)
                               ->first();

            if ($empresaCliente) {
                $skippedCompanies[] = [
                    'id' => $empresa->id,
                    'nombre' => $empresa->nombre,
                    'motivo' => 'Ya tiene credenciales asignadas'
                ];
                $bar->advance();
                continue;
            }

            // Crear cliente OAuth
            $redirectUrl = url('/auth/callback'); // URL de redirección por defecto
            $cliente = $this->clients->create(
                $userId,
                'API Client - ' . $empresa->nombre,
                $redirectUrl,
                null,  // provider
                false, // personalAccess
                false, // password
                true   // confidential
            );

            $cliente_busqueda = DB::table('oauth_clients')
                ->where('name', 'API Client - ' . $empresa->nombre)
                ->first();

            // Asociar cliente con empresa
            $empresaCliente = new EmpresaCliente();
            $empresaCliente->id_empresa = $empresa->id;
            $empresaCliente->id_client = $cliente_busqueda->id;
            $empresaCliente->id_user = $userId;
            $empresaCliente->estado = 1;
            $empresaCliente->save();

            $createdCredentials[] = [
                'empresa_id' => $empresa->id,
                'empresa_nombre' => $empresa->nombre,
                'client_id' => $cliente_busqueda->id,
                'client_secret' => $cliente_busqueda->plainSecret ?? $cliente_busqueda->secret
            ];

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if (count($createdCredentials) > 0) {
            $this->info('Credenciales generadas correctamente:');
            $this->table(
                ['Empresa ID', 'Nombre', 'Client ID', 'Client Secret'],
                $createdCredentials
            );
        }

        if (count($skippedCompanies) > 0) {
            $this->info('Empresas omitidas:');
            $this->table(
                ['ID', 'Nombre', 'Motivo'],
                $skippedCompanies
            );
        }

        $this->info('Proceso completado.');
        return 0;
    }
}
