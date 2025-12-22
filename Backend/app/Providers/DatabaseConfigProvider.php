<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use App\Helpers\AwsConfigHelper;

class DatabaseConfigProvider extends ServiceProvider
{
    public function register()
    {
        // Override database configuration when actually needed
        $this->app->resolving('db', function () {
            if (!Config::get('database.aws_configured', false)) {
                $this->configureDatabase();
                Config::set('database.aws_configured', true);
            }
        });
    }

    private function configureDatabase()
    {
        try {
            $host = AwsConfigHelper::getParameter('/smartpyme/database/host', '127.0.0.1');
            $database = AwsConfigHelper::getParameter('/smartpyme/database/name', 'smartpyme');
            $username = AwsConfigHelper::getSecret('smartpyme/database-credentials', 'username', 'smartpyme_user');
            $password = AwsConfigHelper::getSecret('smartpyme/database-credentials', 'password', '');

            Config::set('database.connections.mysql.host', $host);
            Config::set('database.connections.mysql.database', $database);
            Config::set('database.connections.mysql.username', $username);
            Config::set('database.connections.mysql.password', $password);
            
            error_log("DatabaseConfigProvider: Configuration updated successfully");
        } catch (\Exception $e) {
            // Log error but don't break the app
            error_log("Failed to configure database from AWS: " . $e->getMessage());
        }
    }
}