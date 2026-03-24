<?php

namespace App\Services\Imap;

use App\Models\DteManagement\UserEmailAccount;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

class ImapConnectionService
{
    /**
     * Test IMAP connection with given config.
     *
     * @param array $config ['host', 'port', 'encryption', 'user', 'password']
     * @return bool
     */
    public function testConnection(array $config): bool
    {
        $clientConfig = $this->buildClientConfig($config);
        $client = (new ClientManager())->make($clientConfig);

        try {
            $client->connect();
            $client->disconnect();
            return true;
        } catch (ConnectionFailedException $e) {
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Save IMAP account for user/empresa.
     *
     * @param int $userId
     * @param int $idEmpresa
     * @param array $config
     * @return UserEmailAccount
     */
    public function saveAccount(int $userId, int $idEmpresa, array $config): UserEmailAccount
    {
        if (!$this->testConnection($config)) {
            throw new \RuntimeException('No se pudo conectar al servidor IMAP. Verifique host, puerto, usuario y contraseña.');
        }

        $account = UserEmailAccount::withoutGlobalScopes()->updateOrCreate(
            [
                'id_empresa' => $idEmpresa,
                'email' => $config['user'],
                'provider' => 'imap',
            ],
            [
                'user_id' => $userId,
                'imap_host' => $config['host'],
                'imap_port' => (int) ($config['port'] ?? 993),
                'imap_encryption' => $config['encryption'] ?? 'ssl',
                'imap_user' => $config['user'],
                'imap_password' => $config['password'],
                'id_sucursal' => $config['id_sucursal'] ?? null,
                'id_bodega' => $config['id_bodega'] ?? null,
                'actualizar_inventario' => (bool) ($config['actualizar_inventario'] ?? false),
                'is_active' => true,
            ]
        );

        return $account;
    }

    /**
     * Build config array for webklex/php-imap Client.
     *
     * @param array $config
     * @return array
     */
    protected function buildClientConfig(array $config): array
    {
        $encryption = $config['encryption'] ?? 'ssl';
        $encryptionMap = [
            'ssl' => 'ssl',
            'tls' => 'tls',
            'starttls' => 'starttls',
            'none' => 'notls',
        ];

        return [
            'host' => $config['host'],
            'port' => (int) ($config['port'] ?? 993),
            'encryption' => $encryptionMap[strtolower($encryption)] ?? 'ssl',
            'validate_cert' => true,
            'username' => $config['user'],
            'password' => $config['password'],
            'protocol' => 'imap',
            'timeout' => 30,
        ];
    }
}
